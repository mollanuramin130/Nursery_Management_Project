import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/features/auth/login_screen.dart';
import 'package:nursery_admin_mobile/features/dashboard/dashboard_screen.dart';
import 'package:nursery_admin_mobile/features/fulfillment/fulfillment_screens.dart';
import 'package:nursery_admin_mobile/features/inventory/inventory_screens.dart';
import 'package:nursery_admin_mobile/features/more/more_screens.dart';
import 'package:nursery_admin_mobile/features/orders/orders_screens.dart';
import 'package:nursery_admin_mobile/features/purchasing/purchasing_screens.dart';
import 'package:nursery_admin_mobile/features/shell/admin_shell.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

GoRouter createOpsRouter(AuthProvider auth) {
  return GoRouter(
    initialLocation: '/dashboard',
    refreshListenable: auth,
    redirect: (context, state) {
      if (!auth.bootstrapped) return null;
      final loggingIn = state.matchedLocation == '/login';
      if (!auth.isAuthenticated) {
        return loggingIn ? null : '/login';
      }
      if (loggingIn) return '/dashboard';
      return null;
    },
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            AdminShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/dashboard',
                builder: (context, state) => const DashboardScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/orders',
                builder: (context, state) => const OrdersListScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) => OrderDetailScreen(
                      orderId: int.parse(state.pathParameters['id']!),
                    ),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/inventory',
                builder: (context, state) => const InventoryListScreen(),
                routes: [
                  GoRoute(
                    path: 'low',
                    builder: (context, state) =>
                        const InventoryListScreen(lowOnly: true),
                  ),
                  GoRoute(
                    path: 'movements',
                    builder: (context, state) => const MovementsScreen(),
                  ),
                  GoRoute(
                    path: 'scan',
                    builder: (context, state) => const ScanSkuScreen(),
                  ),
                  GoRoute(
                    path: ':id',
                    builder: (context, state) => InventoryDetailScreen(
                      itemId: int.parse(state.pathParameters['id']!),
                    ),
                    routes: [
                      GoRoute(
                        path: 'adjust',
                        builder: (context, state) => AdjustStockScreen(
                          itemId: int.parse(state.pathParameters['id']!),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/more',
                builder: (context, state) => const MoreScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/fulfillment',
        builder: (context, state) => const FulfillmentHubScreen(),
        routes: [
          GoRoute(
            path: 'queue/:queue',
            builder: (context, state) => FulfillmentQueueScreen(
              queue: state.pathParameters['queue']!,
            ),
          ),
          GoRoute(
            path: 'orders/:id',
            builder: (context, state) => FulfillmentOrderScreen(
              orderId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: '/purchasing',
        builder: (context, state) => const PurchaseOrdersScreen(),
        routes: [
          GoRoute(
            path: ':id',
            builder: (context, state) => PurchaseOrderDetailScreen(
              poId: int.parse(state.pathParameters['id']!),
            ),
            routes: [
              GoRoute(
                path: 'receive',
                builder: (context, state) => ReceiveGoodsScreen(
                  poId: int.parse(state.pathParameters['id']!),
                ),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/suppliers',
        builder: (context, state) => const SuppliersScreen(),
      ),
      GoRoute(
        path: '/warehouses',
        builder: (context, state) => const WarehousesScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/profile',
        builder: (context, state) => const ProfileScreen(),
      ),
    ],
  );
}

class OpsApp extends StatelessWidget {
  const OpsApp({super.key, required this.router});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: AppConfig.appName,
      theme: buildOpsTheme(),
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
