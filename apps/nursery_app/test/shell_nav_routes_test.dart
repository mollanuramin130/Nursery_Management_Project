import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/app.dart';
import 'package:nursery_app/core/shell_nav_policy.dart';

/// QA-36-007 / QA-39 — primary browse/account routes belong to the shell (bottom nav).
void main() {
  test('shell routes match primary bottom destinations', () {
    final router = createRouter();
    final routes = router.configuration.routes;
    expect(routes.first.runtimeType.toString(), contains('StatefulShell'));
  });

  test('ShellNavPolicy keeps tab destinations in shell', () {
    for (final loc in [
      '/',
      '/categories',
      '/catalog',
      '/search',
      '/cart',
      '/orders',
      '/orders/12',
      '/account',
      '/wishlist',
      '/notifications',
      '/offers',
    ]) {
      expect(ShellNavPolicy.isShellLocation(loc), isTrue, reason: loc);
      expect(ShellNavPolicy.isFullscreenLocation(loc), isFalse, reason: loc);
    }
  });

  test('ShellNavPolicy marks auth/PDP/checkout as fullscreen', () {
    for (final loc in [
      '/product/monstera',
      '/checkout',
      '/login',
      '/register',
      '/forgot-password',
      '/account/addresses/new',
    ]) {
      expect(ShellNavPolicy.isFullscreenLocation(loc), isTrue, reason: loc);
      expect(ShellNavPolicy.isShellLocation(loc), isFalse, reason: loc);
    }
  });

  test('order detail prefers in-body actions (no dual bottom chrome)', () {
    expect(ShellNavPolicy.preferInBodyActions('/orders/42'), isTrue);
    expect(ShellNavPolicy.preferInBodyActions('/orders'), isFalse);
    expect(ShellNavPolicy.preferInBodyActions('/cart'), isFalse);
  });

  test('QA-40 cart shell clears sticky commerce above tabs', () {
    expect(ShellNavPolicy.shellHasStickyCommerce('/cart'), isTrue);
    expect(ShellNavPolicy.shellHasStickyCommerce('/cart/'), isTrue);
    expect(ShellNavPolicy.shellHasStickyCommerce('/orders'), isFalse);
    expect(ShellNavPolicy.shellHasStickyCommerce('/'), isFalse);
  });

  test('catalog and wishlist are named shell destinations via location match', () {
    final router = createRouter();
    for (final loc in [
      '/',
      '/categories',
      '/catalog',
      '/search',
      '/cart',
      '/orders',
      '/account',
      '/wishlist',
      '/notifications',
      '/offers',
    ]) {
      expect(
        () => router.configuration.findMatch(Uri.parse(loc)),
        returnsNormally,
        reason: loc,
      );
    }
  });

  test('full-screen flows remain top-level (product/checkout/auth)', () {
    final router = createRouter();
    for (final loc in [
      '/product/monstera',
      '/checkout',
      '/login',
      '/register',
      '/account/addresses/new',
    ]) {
      expect(
        () => router.configuration.findMatch(Uri.parse(loc)),
        returnsNormally,
        reason: loc,
      );
    }
  });
}
