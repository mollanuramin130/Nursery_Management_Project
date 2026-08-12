import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_messages.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_dialog.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  Future<List<Address>>? _future;
  int? _busyId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  void _reload() {
    final user = context.read<AuthProvider>().user;
    if (user == null) {
      setState(() => _future = null);
      return;
    }
    setState(() {
      _future = context.read<ApiClient>().getData(
        '/customer/addresses',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => Address.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    });
  }

  Future<void> _delete(Address address) async {
    final confirmed = await AppDialog.confirm(
      context,
      title: 'Delete address?',
      message: 'This address will be removed from your saved addresses.',
      confirmLabel: 'Delete',
      destructive: true,
    );
    if (!confirmed || !mounted) return;
    setState(() => _busyId = address.id);
    try {
      await context.read<ApiClient>().sendData(
        'DELETE',
        '/customer/addresses/${address.id}',
        map: (_) => null,
      );
      if (!mounted) return;
      AppFeedback.success(context, 'Address removed');
      _reload();
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, AuthMessages.fromException(e));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _setDefault(Address address) async {
    if (address.isDefault) return;
    setState(() => _busyId = address.id);
    try {
      await context.read<ApiClient>().sendData(
        'PUT',
        '/customer/addresses/${address.id}',
        body: {'is_default': true},
        map: (_) => null,
      );
      if (!mounted) return;
      AppFeedback.success(context, 'Default address updated');
      _reload();
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, AuthMessages.fromException(e));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;

    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Addresses')),
        body: EmptyStateView(
          title: 'Sign in to manage addresses',
          message: 'Save delivery addresses for faster checkout.',
          actionLabel: 'Sign in',
          onAction: () =>
              AuthNavigation.pushLogin(context, redirect: '/account/addresses'),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Addresses'),
        actions: [
          IconButton(
            tooltip: 'Add new address',
            onPressed: () async {
              final saved = await context.push<bool>('/account/addresses/new');
              if (saved == true && mounted) _reload();
            },
            icon: const Icon(Icons.add_rounded),
          ),
        ],
      ),
      body: FutureBuilder<List<Address>>(
        future: _future,
        builder: (context, snap) {
          if (_future == null || snap.connectionState != ConnectionState.done) {
            return const AddressesSkeleton();
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load addresses',
              message: AuthMessages.fromException(snap.error!),
              onRetry: _reload,
            );
          }
          final addresses = snap.data ?? [];
          if (addresses.isEmpty) {
            return EmptyStateView(
              title: 'Save an address for faster checkout.',
              message: 'Add a delivery address before your next order.',
              actionLabel: 'Add new address',
              icon: Icons.location_on_outlined,
              onAction: () async {
                final saved = await context.push<bool>(
                  '/account/addresses/new',
                );
                if (saved == true && mounted) _reload();
              },
            );
          }

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.all(AppSpace.screen),
              itemCount: addresses.length + 1,
              separatorBuilder: (context, index) =>
                  const SizedBox(height: AppSpace.md),
              itemBuilder: (context, i) {
                if (i == addresses.length) {
                  return AppButton(
                    label: 'Add new address',
                    variant: AppButtonVariant.secondary,
                    icon: Icons.add_rounded,
                    expanded: true,
                    onPressed: () async {
                      final saved = await context.push<bool>(
                        '/account/addresses/new',
                      );
                      if (saved == true && mounted) _reload();
                    },
                  );
                }
                final a = addresses[i];
                final busy = _busyId == a.id;
                return AppSurfaceCard(
                  selected: a.isDefault,
                  padding: const EdgeInsets.all(AppSpace.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              (a.label?.isNotEmpty == true
                                      ? a.label!
                                      : 'Address')
                                  .toUpperCase(),
                              style: Theme.of(context).textTheme.labelLarge
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryDeep,
                                  ),
                            ),
                          ),
                          if (a.isDefault)
                            const AppBadge(
                              label: 'Default',
                              tone: AppBadgeTone.success,
                            )
                          else
                            TextButton(
                              onPressed: busy ? null : () => _setDefault(a),
                              child: const Text('Set default'),
                            ),
                        ],
                      ),
                      const SizedBox(height: AppSpace.sm),
                      Text(
                        a.name,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(a.phone),
                      Text(a.line1),
                      if (a.line2 != null && a.line2!.isNotEmpty)
                        Text(a.line2!),
                      Text('${a.city}, ${a.state} - ${a.postalCode}'),
                      const SizedBox(height: AppSpace.md),
                      Row(
                        children: [
                          TextButton.icon(
                            onPressed: busy
                                ? null
                                : () async {
                                    final saved = await context.push<bool>(
                                      '/account/addresses/${a.id}/edit',
                                    );
                                    if (saved == true && mounted) _reload();
                                  },
                            icon: const Icon(Icons.edit_outlined, size: 18),
                            label: const Text('Edit'),
                          ),
                          TextButton.icon(
                            onPressed: busy ? null : () => _delete(a),
                            icon: Icon(
                              Icons.delete_outline,
                              size: 18,
                              color: busy ? AppColors.muted : AppColors.error,
                            ),
                            label: Text(
                              busy ? 'Working…' : 'Delete',
                              style: TextStyle(
                                color: busy ? AppColors.muted : AppColors.error,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
