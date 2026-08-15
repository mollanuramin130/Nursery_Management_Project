import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/screens/account_screen.dart';
import 'package:nursery_app/screens/address_form_screen.dart';
import 'package:nursery_app/screens/addresses_screen.dart';
import 'package:nursery_app/screens/cart_screen.dart';
import 'package:nursery_app/screens/campaign_screen.dart';
import 'package:nursery_app/screens/catalog_screen.dart';
import 'package:nursery_app/screens/categories_screen.dart';
import 'package:nursery_app/screens/checkout_screen.dart';
import 'package:nursery_app/screens/find_your_plant_screen.dart';
import 'package:nursery_app/screens/forgot_password_screen.dart';
import 'package:nursery_app/screens/home_screen.dart';
import 'package:nursery_app/screens/login_screen.dart';
import 'package:nursery_app/screens/my_reviews_screen.dart';
import 'package:nursery_app/screens/notifications_screen.dart';
import 'package:nursery_app/screens/offers_screen.dart';
import 'package:nursery_app/screens/order_detail_screen.dart';
import 'package:nursery_app/screens/orders_screen.dart';
import 'package:nursery_app/screens/preferences_screen.dart';
import 'package:nursery_app/screens/product_detail_screen.dart';
import 'package:nursery_app/screens/profile_screen.dart';
import 'package:nursery_app/screens/register_screen.dart';
import 'package:nursery_app/screens/reset_password_screen.dart';
import 'package:nursery_app/screens/return_detail_screen.dart';
import 'package:nursery_app/screens/returns_screen.dart';
import 'package:nursery_app/screens/rewards_screen.dart';
import 'package:nursery_app/screens/subscriptions_screen.dart';
import 'package:nursery_app/screens/search_screen.dart';
import 'package:nursery_app/screens/shell_screen.dart';
import 'package:nursery_app/screens/wishlist_screen.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/screens/splash_screen.dart';
import 'package:nursery_app/theme/app_theme.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';
import 'package:nursery_app/widgets/debug_network_panel.dart';

Widget _fullscreen(Widget child) => FullscreenBackScope(child: child);

/// QA-36-007 — Shell architecture:
/// Primary tabs (Home / Shop / Cart / Orders / Account) keep bottom navigation for
/// browse + account section routes. Full-screen flows (auth, PDP, checkout, address
/// edit) stay outside the shell intentionally.
GoRouter createRouter() {
  return GoRouter(
    initialLocation: '/',
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            ShellScreen(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/',
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          // Shop branch — catalog/search/offers keep bottom nav (QA-36-007).
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/categories',
                builder: (context, state) => const CategoriesScreen(),
              ),
              GoRoute(
                path: '/catalog',
                builder: (context, state) => CatalogScreen(
                  initialFilters:
                      CatalogFilters.fromQuery(state.uri.queryParameters),
                ),
              ),
              GoRoute(
                path: '/search',
                builder: (context, state) =>
                    SearchScreen(initialQuery: state.uri.queryParameters['q']),
              ),
              GoRoute(
                path: '/offers',
                builder: (context, state) => const OffersScreen(),
              ),
              GoRoute(
                path: '/find-your-plant',
                builder: (context, state) => const FindYourPlantScreen(),
              ),
              GoRoute(
                path: '/campaigns/:slug',
                builder: (context, state) =>
                    CampaignScreen(slug: state.pathParameters['slug']!),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/cart',
                builder: (context, state) => const CartScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/orders',
                builder: (context, state) => const OrdersScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) {
                      final id =
                          int.tryParse(state.pathParameters['id'] ?? '');
                      if (id == null) {
                        return Scaffold(
                          appBar: AppBar(
                            title: const Text('Order'),
                            leading: const GreenLeafBackButton(
                              fallback: '/orders',
                            ),
                          ),
                          body: const Center(
                            child: Text('Invalid order link.'),
                          ),
                        );
                      }
                      return OrderDetailScreen(
                        orderId: id,
                        placedNumber: state.uri.queryParameters['placed'],
                        paymentPending:
                            state.uri.queryParameters['pay'] == 'pending',
                        paid: state.uri.queryParameters['paid'] == '1',
                      );
                    },
                  ),
                ],
              ),
            ],
          ),
          // Account branch — wishlist/notifications/account subpages keep tabs.
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/account',
                builder: (context, state) => const AccountScreen(),
                routes: [
                  GoRoute(
                    path: 'profile',
                    builder: (context, state) => const ProfileScreen(),
                  ),
                  GoRoute(
                    path: 'rewards',
                    builder: (context, state) => const RewardsScreen(),
                  ),
                  GoRoute(
                    path: 'subscriptions',
                    builder: (context, state) => const SubscriptionsScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) {
                          final id =
                              int.tryParse(state.pathParameters['id'] ?? '');
                          if (id == null) {
                            return Scaffold(
                              appBar: AppBar(title: const Text('Subscription')),
                              body: const Center(
                                child: Text('Invalid subscription link.'),
                              ),
                            );
                          }
                          return SubscriptionDetailScreen(id: id);
                        },
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'preferences',
                    builder: (context, state) => const PreferencesScreen(),
                  ),
                  GoRoute(
                    path: 'returns',
                    builder: (context, state) => const ReturnsScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) {
                          final id =
                              int.tryParse(state.pathParameters['id'] ?? '');
                          if (id == null) {
                            return Scaffold(
                              appBar: AppBar(title: const Text('Return')),
                              body: const Center(
                                child: Text('Invalid return link.'),
                              ),
                            );
                          }
                          return ReturnDetailScreen(returnId: id);
                        },
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'reviews',
                    builder: (context, state) => const MyReviewsScreen(),
                  ),
                ],
              ),
              GoRoute(
                path: '/wishlist',
                builder: (context, state) => const WishlistScreen(),
              ),
              GoRoute(
                path: '/notifications',
                builder: (context, state) => const NotificationsScreen(),
              ),
            ],
          ),
        ],
      ),
      // Full-screen flows — no bottom navigation (intentional).
      GoRoute(
        path: '/product/:slug',
        builder: (context, state) => _fullscreen(
          ProductDetailScreen(
            slug: state.pathParameters['slug']!,
            openReviewForm: state.uri.queryParameters['review'] == '1',
          ),
        ),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => _fullscreen(
          LoginScreen(redirectTo: state.uri.queryParameters['redirect']),
        ),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => _fullscreen(
          RegisterScreen(redirectTo: state.uri.queryParameters['redirect']),
        ),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => _fullscreen(
          ForgotPasswordScreen(
            redirectTo: state.uri.queryParameters['redirect'],
          ),
        ),
      ),
      GoRoute(
        path: '/reset-password',
        builder: (context, state) => _fullscreen(
          ResetPasswordScreen(
            email: state.uri.queryParameters['email'],
            token: state.uri.queryParameters['token'],
            redirectTo: state.uri.queryParameters['redirect'],
          ),
        ),
      ),
      // Address list + forms use sticky save bars — keep outside shell.
      GoRoute(
        path: '/account/addresses',
        builder: (context, state) => _fullscreen(const AddressesScreen()),
      ),
      GoRoute(
        path: '/account/addresses/new',
        builder: (context, state) => _fullscreen(const AddressFormScreen()),
      ),
      GoRoute(
        path: '/account/addresses/:id/edit',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          if (id == null) {
            return _fullscreen(
              Scaffold(
                appBar: AppBar(
                  title: const Text('Address'),
                  leading: const GreenLeafBackButton(fallback: '/account'),
                ),
                body: const Center(child: Text('Invalid address link.')),
              ),
            );
          }
          return _fullscreen(AddressFormScreen(addressId: id));
        },
      ),
      GoRoute(
        path: '/checkout',
        builder: (context, state) => _fullscreen(const CheckoutScreen()),
      ),
    ],
  );
}

class NurseryApp extends StatelessWidget {
  const NurseryApp({
    super.key,
    required this.router,
    this.showSplash = false,
    this.onSplashFinished,
  });

  final GoRouter router;
  final bool showSplash;
  final VoidCallback? onSplashFinished;

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'GreenLeaf Nursery',
      theme: AppTheme.light,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
      // QA panel must sit under MaterialApp (Directionality), not outside it.
      builder: (context, child) {
        Widget content = child ?? const SizedBox.shrink();
        if (allowDebugNetworkPanel) {
          content = DebugNetworkOverlay(child: content);
        }
        if (showSplash && onSplashFinished != null) {
          content = Stack(
            fit: StackFit.expand,
            children: [
              content,
              GreenLeafSplashScreen(onFinished: onSplashFinished!),
            ],
          );
        }
        return content;
      },
    );
  }
}
