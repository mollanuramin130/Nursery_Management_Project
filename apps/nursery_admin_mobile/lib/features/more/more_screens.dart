import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/app_error.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';
import 'package:nursery_admin_mobile/services/notification_deep_link.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView(
        children: [
          if (auth.can('fulfillment.view'))
            ListTile(
              leading: const Icon(Icons.local_shipping_outlined),
              title: const Text('Fulfillment'),
              subtitle: const Text('Pick · pack · ship · deliver'),
              onTap: () => context.push('/fulfillment'),
            ),
          if (auth.canAny(['purchase_orders.view', 'inventory.adjust']))
            ListTile(
              leading: const Icon(Icons.shopping_bag_outlined),
              title: const Text('Purchase orders'),
              onTap: () => context.push('/purchasing'),
            ),
          if (auth.canAny(['suppliers.view', 'inventory.adjust', 'suppliers.manage']))
            ListTile(
              leading: const Icon(Icons.storefront_outlined),
              title: const Text('Suppliers'),
              onTap: () => context.push('/suppliers'),
            ),
          if (auth.canAny(['inventory.view', 'warehouses.view']))
            ListTile(
              leading: const Icon(Icons.warehouse_outlined),
              title: const Text('Warehouses'),
              onTap: () => context.push('/warehouses'),
            ),
          if (auth.canAny(['inventory.transfer', 'inventory.adjust', 'inventory.view']))
            ListTile(
              leading: const Icon(Icons.swap_horiz),
              title: const Text('Stock movements'),
              subtitle: const Text(
                'Inventory adjustments & history (complex transfers: Admin Web)',
              ),
              onTap: () => context.push('/inventory/movements'),
            ),
          if (auth.can('inventory.view'))
            ListTile(
              leading: const Icon(Icons.warning_amber_outlined),
              title: const Text('Low stock'),
              onTap: () => context.push('/inventory/low'),
            ),
          if (auth.can('notifications.view'))
            ListTile(
              leading: const Icon(Icons.notifications_outlined),
              title: const Text('Notifications'),
              onTap: () => context.push('/notifications'),
            ),
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: const Text('Profile'),
            onTap: () => context.push('/profile'),
          ),
        ],
      ),
    );
  }
}

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: user == null
          ? const OpsEmpty(title: 'Not signed in')
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(user.name,
                    style: Theme.of(context)
                        .textTheme
                        .headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w800)),
                Text(user.email, style: const TextStyle(color: OpsColors.muted)),
                const SizedBox(height: 12),
                Text('Roles: ${user.roles.join(', ')}'),
                const SizedBox(height: 8),
                Text(
                  'Permissions: ${user.permissions.take(12).join(', ')}'
                  '${user.permissions.length > 12 ? '…' : ''}',
                  style: const TextStyle(fontSize: 12, color: OpsColors.muted),
                ),
                const Divider(height: 32),
                Text('App: ${AppConfig.appName}'),
                Text('Version: ${AppConfig.appVersion}'),
                const SizedBox(height: 24),
                FilledButton(
                  style: FilledButton.styleFrom(backgroundColor: OpsColors.danger),
                  onPressed: () async {
                    final ok = await confirmAction(
                      context,
                      title: 'Sign out?',
                      body: 'You will need to sign in again.',
                      confirmLabel: 'Sign out',
                      danger: true,
                    );
                    if (!ok || !context.mounted) return;
                    await auth.logout();
                  },
                  child: const Text('Sign out'),
                ),
              ],
            ),
    );
  }
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final soft = _rows.isNotEmpty;
    if (mounted) {
      setState(() {
        if (!soft) _loading = true;
        _error = null;
      });
    }
    try {
      final api = context.read<ApiClient>();
      final data = await api.getData(
        '/notifications',
        map: (d) => (d as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
      );
      _rows
        ..clear()
        ..addAll(data);
    } catch (e) {
      _error = sanitizeCaughtError(e);
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: _loading && _rows.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _rows.isEmpty
              ? OpsError(message: _error!, onRetry: _load)
              : _rows.isEmpty
                  ? const OpsEmpty(
                      title: 'No notifications',
                      subtitle: 'Operational alerts will appear here.',
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        itemCount: _rows.length,
                        itemBuilder: (context, i) {
                          final n = _rows[i];
                          return ListTile(
                            title: Text(n['title']?.toString() ?? 'Notification'),
                            subtitle: Text(n['body']?.toString() ?? ''),
                            trailing: Text(
                              n['category']?.toString() ?? '',
                              style: const TextStyle(fontSize: 11),
                            ),
                            onTap: () {
                              final data = n['data'] is Map
                                  ? Map<String, dynamic>.from(n['data'] as Map)
                                  : null;
                              final href = adminNotificationDeepLink(data);
                              if (href != null) {
                                context.push(href);
                              }
                            },
                          );
                        },
                      ),
                    ),
    );
  }
}
