import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';

class AdminShell extends StatelessWidget {
  const AdminShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final destinations = <_Dest>[
      const _Dest(label: 'Home', icon: Icons.dashboard_outlined, selected: Icons.dashboard),
      if (auth.can('orders.view'))
        const _Dest(label: 'Orders', icon: Icons.receipt_long_outlined, selected: Icons.receipt_long),
      if (auth.can('inventory.view'))
        const _Dest(label: 'Stock', icon: Icons.inventory_2_outlined, selected: Icons.inventory_2),
      const _Dest(label: 'More', icon: Icons.more_horiz, selected: Icons.more_horiz),
    ];

    // Map shell branch index carefully: branches are fixed in router.
    final branchCount = navigationShell.route.branches.length;
    final visibleIndexes = <int>[0]; // dashboard always
    if (auth.can('orders.view')) visibleIndexes.add(1);
    if (auth.can('inventory.view')) visibleIndexes.add(2);
    visibleIndexes.add(branchCount - 1); // more

    final currentBranch = navigationShell.currentIndex;
    var selected = visibleIndexes.indexOf(currentBranch);
    if (selected < 0) selected = 0;

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selected.clamp(0, destinations.length - 1),
        onDestinationSelected: (i) {
          final branch = visibleIndexes[i.clamp(0, visibleIndexes.length - 1)];
          navigationShell.goBranch(
            branch,
            initialLocation: branch == navigationShell.currentIndex,
          );
        },
        destinations: destinations
            .map(
              (d) => NavigationDestination(
                icon: Icon(d.icon),
                selectedIcon: Icon(d.selected),
                label: d.label,
              ),
            )
            .toList(),
      ),
    );
  }
}

class _Dest {
  const _Dest({
    required this.label,
    required this.icon,
    required this.selected,
  });

  final String label;
  final IconData icon;
  final IconData selected;
}

/// Simple top bar helper for nested pages when needed.
PreferredSizeWidget opsAppBar(String title) => AppBar(
      title: Text(title),
      actions: [
        Padding(
          padding: const EdgeInsets.only(right: 12),
          child: Center(
            child: Text(
              AppConfig.appVersion,
              style: const TextStyle(fontSize: 11, color: Colors.white70),
            ),
          ),
        ),
      ],
    );
