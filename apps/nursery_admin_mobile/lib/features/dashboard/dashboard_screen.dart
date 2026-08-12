import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final dash = context.watch<DashboardProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(AppConfig.appName),
        actions: [
          if (auth.can('notifications.view'))
            IconButton(
              tooltip: 'Notifications',
              onPressed: () => context.push('/notifications'),
              icon: const Icon(Icons.notifications_outlined),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => dash.load(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Hello, ${auth.user?.name.split(' ').first ?? 'Staff'}',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            Text(
              auth.user?.roles.join(' · ') ?? '',
              style: const TextStyle(color: OpsColors.muted),
            ),
            const SizedBox(height: 16),
            if (dash.loading && dash.inventoryDash == null)
              const Center(child: CircularProgressIndicator()),
            if (dash.error != null)
              OpsError(message: dash.error!, onRetry: dash.load),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 8,
              crossAxisSpacing: 8,
              childAspectRatio: 1.45,
              children: [
                if (auth.can('orders.view'))
                  KpiTile(
                    label: 'Pending / confirmed',
                    value: '${dash.pendingOrders ?? '—'}',
                    onTap: () => context.go('/orders'),
                  ),
                if (auth.can('inventory.view'))
                  KpiTile(
                    label: 'Low stock',
                    value: '${dash.lowStock ?? '—'}',
                    onTap: () => context.push('/inventory/low'),
                  ),
                if (auth.can('inventory.view'))
                  KpiTile(
                    label: 'Out of stock',
                    value: '${dash.outOfStock ?? '—'}',
                    onTap: () => context.push('/inventory/low'),
                  ),
                if (auth.canAny(['purchase_orders.view', 'inventory.adjust']))
                  KpiTile(
                    label: 'Open POs',
                    value: '${dash.pendingPos ?? '—'}',
                    onTap: () => context.go('/purchasing'),
                  ),
              ],
            ),
            const SizedBox(height: 20),
            Text(
              'Quick actions',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (auth.can('orders.view'))
                  ActionChip(
                    label: const Text('Orders'),
                    onPressed: () => context.go('/orders'),
                  ),
                if (auth.can('inventory.view'))
                  ActionChip(
                    label: const Text('Inventory'),
                    onPressed: () => context.go('/inventory'),
                  ),
                if (auth.can('inventory.view'))
                  ActionChip(
                    label: const Text('Scan SKU'),
                    onPressed: () => context.push('/inventory/scan'),
                  ),
                if (auth.canAny(['purchase_orders.view', 'inventory.adjust']))
                  ActionChip(
                    label: const Text('Receive PO'),
                    onPressed: () => context.go('/purchasing'),
                  ),
                if (auth.can('inventory.adjust'))
                  ActionChip(
                    label: const Text('Adjust stock'),
                    onPressed: () => context.go('/inventory'),
                  ),
              ],
            ),
            if (auth.can('orders.view')) ...[
              const SizedBox(height: 20),
              Text(
                'Confirmed orders',
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              if (dash.recentOrders.isEmpty)
                const Text('No confirmed orders.', style: TextStyle(color: OpsColors.muted)),
              ...dash.recentOrders.map(
                (o) => Card(
                  child: ListTile(
                    title: Text(o.orderNumber),
                    subtitle: Text('${o.customerName ?? '—'} · ${money(o.grandTotal)}'),
                    trailing: OpsStatusChip(o.status),
                    onTap: () => context.push('/orders/${o.id}'),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
