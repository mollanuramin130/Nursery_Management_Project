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
import 'package:nursery_app/screens/notifications_screen.dart';
import 'package:nursery_app/screens/offers_screen.dart';
import 'package:nursery_app/screens/order_detail_screen.dart';
import 'package:nursery_app/screens/orders_screen.dart';
import 'package:nursery_app/screens/preferences_screen.dart';
import 'package:nursery_app/screens/product_detail_screen.dart';
import 'package:nursery_app/screens/profile_screen.dart';
import 'package:nursery_app/screens/register_screen.dart';
import 'package:nursery_app/screens/rewards_screen.dart';
import 'package:nursery_app/screens/subscriptions_screen.dart';
import 'package:nursery_app/screens/search_screen.dart';
import 'package:nursery_app/screens/shell_screen.dart';
import 'package:nursery_app/screens/wishlist_screen.dart';
import 'package:nursery_app/theme/app_theme.dart';
import 'package:nursery_app/widgets/catalog_filters.dart';

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
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/categories',
                builder: (context, state) => const CategoriesScreen(),
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
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/account',
                builder: (context, state) => const AccountScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/catalog',
        builder: (context, state) => CatalogScreen(
          initialFilters: CatalogFilters.fromQuery(state.uri.queryParameters),
        ),
      ),
      GoRoute(
        path: '/search',
        builder: (context, state) =>
            SearchScreen(initialQuery: state.uri.queryParameters['q']),
      ),
      GoRoute(
        path: '/campaigns/:slug',
        builder: (context, state) =>
            CampaignScreen(slug: state.pathParameters['slug']!),
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
        path: '/product/:slug',
        builder: (context, state) => ProductDetailScreen(
          slug: state.pathParameters['slug']!,
          openReviewForm: state.uri.queryParameters['review'] == '1',
        ),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) =>
            LoginScreen(redirectTo: state.uri.queryParameters['redirect']),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) =>
            RegisterScreen(redirectTo: state.uri.queryParameters['redirect']),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => ForgotPasswordScreen(
          redirectTo: state.uri.queryParameters['redirect'],
        ),
      ),
      GoRoute(
        path: '/account/profile',
        builder: (context, state) => const ProfileScreen(),
      ),
      GoRoute(
        path: '/account/rewards',
        builder: (context, state) => const RewardsScreen(),
      ),
      GoRoute(
        path: '/account/subscriptions',
        builder: (context, state) => const SubscriptionsScreen(),
      ),
      GoRoute(
        path: '/account/subscriptions/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          if (id == null) {
            return Scaffold(
              appBar: AppBar(title: const Text('Subscription')),
              body: const Center(child: Text('Invalid subscription link.')),
            );
          }
          return SubscriptionDetailScreen(id: id);
        },
      ),
      GoRoute(
        path: '/account/preferences',
        builder: (context, state) => const PreferencesScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/account/addresses',
        builder: (context, state) => const AddressesScreen(),
      ),
      GoRoute(
        path: '/account/addresses/new',
        builder: (context, state) => const AddressFormScreen(),
      ),
      GoRoute(
        path: '/account/addresses/:id/edit',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          if (id == null) {
            return Scaffold(
              appBar: AppBar(title: const Text('Address')),
              body: const Center(child: Text('Invalid address link.')),
            );
          }
          return AddressFormScreen(addressId: id);
        },
      ),
      GoRoute(
        path: '/wishlist',
        builder: (context, state) => const WishlistScreen(),
      ),
      GoRoute(
        path: '/checkout',
        builder: (context, state) => const CheckoutScreen(),
      ),
      GoRoute(
        path: '/orders/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '');
          if (id == null) {
            return Scaffold(
              appBar: AppBar(title: const Text('Order')),
              body: const Center(child: Text('Invalid order link.')),
            );
          }
          return OrderDetailScreen(
            orderId: id,
            placedNumber: state.uri.queryParameters['placed'],
            paymentPending: state.uri.queryParameters['pay'] == 'pending',
            paid: state.uri.queryParameters['paid'] == '1',
          );
        },
      ),
    ],
  );
}

class NurseryApp extends StatelessWidget {
  const NurseryApp({super.key, required this.router});

  final GoRouter router;

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'GreenLeaf Nursery',
      theme: AppTheme.light,
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
