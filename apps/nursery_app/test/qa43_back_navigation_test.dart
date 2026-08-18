import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/app.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/core/shell_nav_policy.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/network_status_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:lottie/lottie.dart';
import 'package:nursery_app/screens/splash_screen.dart';
import 'package:provider/provider.dart';

/// QA-43 — navigation / splash / icon configuration regressions.
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<GoRouter> pumpRouter(WidgetTester tester, {String? location}) async {
    final storage = SessionStorage();
    final local = OfflineLocalStore();
    final offline = OfflineController(mode: MockDataMode.mockOnly);
    final api = ApiClient(storage);
    final catalog = CatalogRepository(api: api, offline: offline, local: local);
    final router = createRouter();
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: api),
          Provider<SessionStorage>.value(value: storage),
          Provider<CatalogRepository>.value(value: catalog),
          ChangeNotifierProvider<OfflineController>.value(value: offline),
          ChangeNotifierProvider<AuthProvider>.value(
            value: AuthProvider(api, storage),
          ),
          ChangeNotifierProvider<CartProvider>.value(
            value: CartProvider(api, offline: offline, local: local)
              ..attachCatalog(catalog),
          ),
          ChangeNotifierProvider<WishlistProvider>.value(
            value: WishlistProvider(api, offline: offline, local: local),
          ),
          ChangeNotifierProvider<NetworkStatusProvider>.value(
            value: NetworkStatusProvider(),
          ),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    if (location != null) {
      router.go(location);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));
    }
    return router;
  }

  group('QA-43 back navigation policy', () {
    test('QA-43-006 root only when Home + empty stack', () {
      final router = createRouter();
      expect(
        BackNavigation.isAppRoot(router: router, shellBranchIndex: 0),
        isTrue,
      );
      expect(
        BackNavigation.isAppRoot(router: router, shellBranchIndex: 1),
        isFalse,
      );
    });

    test('QA-43-007 in-branch parents do not jump Home', () {
      expect(BackNavigation.parentInsideBranch('/catalog'), '/categories');
      expect(BackNavigation.parentInsideBranch('/search'), '/categories');
      expect(BackNavigation.parentInsideBranch('/orders/12'), '/orders');
      expect(BackNavigation.parentInsideBranch('/account/profile'), '/account');
      expect(BackNavigation.parentInsideBranch('/wishlist'), '/account');
      expect(BackNavigation.parentInsideBranch('/categories'), isNull);
      expect(BackNavigation.parentInsideBranch('/'), isNull);
    });

    test('QA-43-005 deep fullscreen fallback is safe parent', () {
      expect(BackNavigation.fallbackFor('/product/snake-plant'), '/');
      expect(BackNavigation.fallbackFor('/checkout'), '/cart');
      expect(BackNavigation.fallbackFor('/account/addresses'), '/account');
      expect(BackNavigation.fallbackFor('/login'), '/');
    });

    testWidgets('QA-43-001 product detail is fullscreen + pops to prior',
        (tester) async {
      final router = await pumpRouter(tester);
      router.go('/');
      await tester.pump();
      router.push('/product/monstera');
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));

      expect(ShellNavPolicy.isFullscreenLocation('/product/monstera'), isTrue);
      expect(router.canPop(), isTrue);

      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-002 order detail stays in shell and pops to orders',
        (tester) async {
      final router = await pumpRouter(tester);
      router.go('/orders');
      await tester.pump();
      router.push('/orders/42');
      await tester.pump();

      expect(ShellNavPolicy.isShellLocation('/orders/42'), isTrue);
      expect(router.canPop(), isTrue);
      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/orders');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-003 checkout is fullscreen and can pop to cart',
        (tester) async {
      final router = await pumpRouter(tester);
      router.go('/cart');
      await tester.pump();
      router.push('/checkout');
      await tester.pump();

      expect(ShellNavPolicy.isFullscreenLocation('/checkout'), isTrue);
      expect(router.canPop(), isTrue);
      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/cart');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-004 account subpage pops within shell', (tester) async {
      final router = await pumpRouter(tester);
      router.go('/account');
      await tester.pump();
      router.push('/account/reviews');
      await tester.pump();
      expect(router.canPop(), isTrue);
      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/account');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-005 deep product route is fullscreen safe',
        (tester) async {
      final router = await pumpRouter(tester, location: '/product/snake-plant');
      expect(ShellNavPolicy.isFullscreenLocation(router.state.uri.path), isTrue);
      expect(router.state.uri.path, '/product/snake-plant');
      expect(
        BackNavigation.fallbackFor(router.state.uri.path),
        '/',
      );
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-007 shop catalog push preserves back to categories',
        (tester) async {
      final router = await pumpRouter(tester);
      router.go('/categories');
      await tester.pump();
      router.push('/catalog');
      await tester.pump();
      expect(router.canPop(), isTrue);
      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/categories');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-008 login is fullscreen (logout lands outside shell)',
        (tester) async {
      expect(ShellNavPolicy.isFullscreenLocation('/login'), isTrue);
      final router = await pumpRouter(tester, location: '/login');
      expect(router.state.uri.path, '/login');
      await tester.pump(const Duration(seconds: 3));
    });

    testWidgets('QA-43-015 addresses edit stack pops safely', (tester) async {
      final router = await pumpRouter(tester);
      router.go('/account/addresses');
      await tester.pump();
      router.push('/account/addresses/3/edit');
      await tester.pump();
      expect(router.canPop(), isTrue);
      router.pop();
      await tester.pump();
      expect(router.state.uri.path, '/account/addresses');
      await tester.pump(const Duration(seconds: 3));
    });
  });

  group('QA-43 splash', () {
    testWidgets('QA-43-009/012 splash finishes without waiting forever',
        (tester) async {
      var finished = false;
      await tester.pumpWidget(
        MaterialApp(
          home: GreenLeafSplashScreen(
            minDisplay: const Duration(milliseconds: 50),
            onFinished: () => finished = true,
          ),
        ),
      );
      expect(find.byType(Lottie), findsOneWidget);
      expect(find.text('GreenLeaf'), findsOneWidget);
      expect(find.text('Grow Better. Live Greener.'), findsOneWidget);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 80));
      expect(finished, isTrue);
    });

    testWidgets('QA-43-010 offline splash still completes', (tester) async {
      var finished = false;
      await tester.pumpWidget(
        MaterialApp(
          home: GreenLeafSplashScreen(
            minDisplay: const Duration(milliseconds: 20),
            onFinished: () => finished = true,
          ),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 40));
      expect(finished, isTrue);
    });

    test('QA-43-011 splash is not an API gate (policy)', () {
      final src = File('lib/screens/splash_screen.dart').readAsStringSync();
      expect(src.contains('ApiClient'), isFalse);
      expect(src.contains('CatalogRepository'), isFalse);
      expect(src.contains('minDisplay'), isTrue);
      expect(src.contains('maxDisplay'), isTrue);
      expect(src.contains('must not wait for API'), isTrue);
    });

    testWidgets('QA-43-015 splash respects maxDisplay even if minDisplay is long',
        (tester) async {
      var finished = false;
      await tester.pumpWidget(
        MaterialApp(
          home: GreenLeafSplashScreen(
            minDisplay: const Duration(seconds: 30),
            maxDisplay: const Duration(milliseconds: 40),
            onFinished: () => finished = true,
          ),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 80));
      expect(finished, isTrue);
    });

    testWidgets('QA-43-016 reduced motion still reveals the wordmark',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: MediaQuery(
            data: const MediaQueryData(disableAnimations: true),
            child: GreenLeafSplashScreen(
              minDisplay: const Duration(milliseconds: 20),
              onFinished: () {},
            ),
          ),
        ),
      );
      expect(find.text('GreenLeaf'), findsOneWidget);
      expect(find.text('Grow Better. Live Greener.'), findsOneWidget);
      expect(find.byType(Lottie), findsOneWidget);
    });
  });

  group('QA-43 launcher icon configuration', () {
    test('QA-43-013 adaptive icon does not circular-reference mipmap', () {
      final xml = File(
        'android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml',
      ).readAsStringSync();
      expect(xml.contains('@drawable/ic_launcher_foreground'), isTrue);
      expect(xml.contains('android:drawable="@mipmap/ic_launcher"'), isFalse);
    });

    test('QA-43-013 density mipmaps exist', () {
      for (final d in [
        'mipmap-mdpi',
        'mipmap-hdpi',
        'mipmap-xhdpi',
        'mipmap-xxhdpi',
        'mipmap-xxxhdpi',
      ]) {
        final f = File('android/app/src/main/res/$d/ic_launcher.png');
        expect(f.existsSync(), isTrue, reason: d);
        expect(f.lengthSync(), greaterThan(200));
      }
    });

    test('QA-43-017 customer adaptive mark is a seedling, not a shield', () {
      final xml = File(
        'android/app/src/main/res/drawable/ic_launcher_foreground.xml',
      ).readAsStringSync();
      expect(xml.contains('seedling'), isTrue);
      expect(xml.contains('shield'), isFalse);
    });
  });
}
