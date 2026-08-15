import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/soft_future_refresh.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/services/notification_deep_link.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<_Inbox> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_Inbox> _load() async {
    final result = await context.read<ApiClient>().getDataWithMeta(
      '/notifications',
      query: {'per_page': 30},
      map: (data) => (data as List)
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
    );
    final unread = (result.meta?['unread_count'] as num?)?.toInt() ?? 0;
    return _Inbox(items: result.data, unread: unread);
  }

  String? _href(Map<String, dynamic> n) {
    final data = n['data'];
    if (data is! Map) return null;
    return notificationDeepLink(Map<String, dynamic>.from(data));
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Notifications')),
        body: EmptyStateView(
          title: 'Notifications',
          message: 'Sign in to see order and offer updates.',
          actionLabel: 'Sign in',
          onAction: () => AuthNavigation.pushLogin(
            context,
            redirect: '/notifications',
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: () async {
              await context.read<ApiClient>().sendData(
                'POST',
                '/notifications/read-all',
                map: (_) => null,
              );
              if (!mounted) return;
              await softReplaceFuture(
                load: _load,
                onData: (data) {
                  if (!mounted) return;
                  setState(() => _future = completedFuture(data));
                },
              );
            },
            child: const Text('Mark all'),
          ),
        ],
      ),
      body: FutureBuilder<_Inbox>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return ErrorStateView(
              title: 'Unable to load',
              message: ErrorStateView.sanitize(snap.error?.toString()),
              onRetry: () {
                softReplaceFuture(
                  load: _load,
                  onData: (data) {
                    if (!mounted) return;
                    setState(() => _future = completedFuture(data));
                  },
                );
              },
            );
          }
          final inbox = snap.data!;
          if (inbox.items.isEmpty) {
            return const EmptyStateView(
              title: "You're all caught up.",
              message: 'Order and offer updates will appear here.',
            );
          }
          return RefreshIndicator(
            onRefresh: () async {
              await softReplaceFuture(
                load: _load,
                onData: (data) {
                  if (!mounted) return;
                  setState(() => _future = completedFuture(data));
                },
                onError: (e) {
                  if (!mounted) return;
                  AppFeedback.error(context, e.toString());
                },
              );
            },
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: inbox.items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final n = inbox.items[i];
                final unread = n['is_read'] != true;
                final href = _href(n);
                return ListTile(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: AppColors.border),
                  ),
                  tileColor: unread
                      ? AppColors.primarySoft.withValues(alpha: 0.35)
                      : null,
                  title: Text(
                    n['title']?.toString() ?? 'Update',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(n['body']?.toString() ?? ''),
                  trailing: unread
                      ? const Icon(Icons.circle, size: 10, color: AppColors.primary)
                      : null,
                  onTap: () async {
                    if (unread) {
                      await context.read<ApiClient>().sendData(
                        'POST',
                        '/notifications/${n['id']}/read',
                        map: (_) => null,
                      );
                    }
                    if (!context.mounted) return;
                    if (href != null) {
                      context.push(href);
                    } else {
                      await softReplaceFuture(
                        load: _load,
                        onData: (data) {
                          if (!mounted) return;
                          setState(() => _future = completedFuture(data));
                        },
                      );
                    }
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _Inbox {
  _Inbox({required this.items, required this.unread});
  final List<Map<String, dynamic>> items;
  final int unread;
}
