import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/network_status_banner.dart';
import 'package:provider/provider.dart';

/// Application shell with botanical bottom navigation.
class ShellScreen extends StatelessWidget {
  const ShellScreen({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context) {
    return ShellBackScope(
      navigationShell: navigationShell,
      child: Scaffold(
        body: Column(
          children: [
            const NetworkStatusBanner(),
            Expanded(child: navigationShell),
          ],
        ),
        bottomNavigationBar: Material(
          color: AppColors.surface,
          elevation: 0,
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: AppColors.surface,
              border: const Border(top: BorderSide(color: AppColors.border)),
              boxShadow: AppShadows.sm,
            ),
            child: Selector<CartProvider, int>(
              selector: (_, cart) => cart.cart.itemCount,
              builder: (context, cartCount, _) {
                return NavigationBar(
                  selectedIndex: navigationShell.currentIndex,
                  onDestinationSelected: (index) => navigationShell.goBranch(
                    index,
                    initialLocation: index == navigationShell.currentIndex,
                  ),
                  destinations: [
                    const NavigationDestination(
                      icon: Icon(Icons.home_outlined),
                      selectedIcon: Icon(Icons.home_rounded),
                      label: 'Home',
                      tooltip: 'Home',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.grid_view_outlined),
                      selectedIcon: Icon(Icons.grid_view_rounded),
                      label: 'Shop',
                      tooltip: 'Categories',
                    ),
                    NavigationDestination(
                      icon: Badge(
                        isLabelVisible: cartCount > 0,
                        label: Text(cartCount > 99 ? '99+' : '$cartCount'),
                        child: const Icon(Icons.shopping_bag_outlined),
                      ),
                      selectedIcon: Badge(
                        isLabelVisible: cartCount > 0,
                        label: Text(cartCount > 99 ? '99+' : '$cartCount'),
                        child: const Icon(Icons.shopping_bag_rounded),
                      ),
                      label: 'Cart',
                      tooltip: cartCount > 0 ? 'Cart, $cartCount items' : 'Cart',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.receipt_long_outlined),
                      selectedIcon: Icon(Icons.receipt_long_rounded),
                      label: 'Orders',
                      tooltip: 'Orders',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.person_outline_rounded),
                      selectedIcon: Icon(Icons.person_rounded),
                      label: 'Account',
                      tooltip: 'Account',
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}
