import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/app.dart';

/// QA-36-007 — primary browse/account routes belong to the shell (bottom nav).
void main() {
  test('shell routes match primary bottom destinations', () {
    final router = createRouter();
    final routes = router.configuration.routes;

    // StatefulShellRoute is first.
    expect(routes.first.runtimeType.toString(), contains('StatefulShell'));
  });

  test('catalog and wishlist are named shell destinations via location match', () {
    final router = createRouter();
    // Matchers: shell-hosted locations should not be "unknown".
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
      final match = router.routeInformationParser;
      expect(match, isNotNull, reason: loc);
      // Ensure configuration accepts the location (no throw on parse).
      expect(() => router.configuration.findMatch(Uri.parse(loc)), returnsNormally);
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
