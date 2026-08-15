/// QA-39 — single source of truth for Customer Mobile shell vs fullscreen chrome.
///
/// Primary bottom destinations (always via [StatefulShellRoute]):
/// Home · Shop · Cart · Orders · Account
///
/// Fullscreen flows intentionally hide the tab bar (own sticky CTAs / auth).
library;

abstract final class ShellNavPolicy {
  /// Height of Material 3 [NavigationBar] used by [ShellScreen].
  static const double tabBarHeight = 64;

  /// Locations that belong inside the indexed shell (keep bottom tabs).
  static bool isShellLocation(String location) {
    if (isFullscreenLocation(location)) return false;
    final path = Uri.tryParse(location)?.path ?? location;
    if (path == '/' || path.isEmpty) return true;
    const shellPrefixes = <String>[
      '/categories',
      '/catalog',
      '/search',
      '/offers',
      '/find-your-plant',
      '/campaigns',
      '/cart',
      '/orders',
      '/account',
      '/wishlist',
      '/notifications',
    ];
    for (final p in shellPrefixes) {
      if (path == p || path.startsWith('$p/')) return true;
    }
    return false;
  }

  /// Fullscreen routes — no shell bottom navigation (intentional).
  static bool isFullscreenLocation(String location) {
    final path = Uri.tryParse(location)?.path ?? location;
    const fullscreenPrefixes = <String>[
      '/product/',
      '/checkout',
      '/login',
      '/register',
      '/forgot-password',
      '/reset-password',
      '/account/addresses',
    ];
    for (final p in fullscreenPrefixes) {
      if (path == p || path.startsWith(p)) return true;
    }
    // Address create/edit outside shell.
    if (path.startsWith('/account/addresses')) return true;
    return false;
  }

  /// Nested scaffolds inside the shell must not add a second system SafeArea
  /// bottom pad — the tab bar already owns that inset.
  static bool nestedStickyUsesSafeBottom(String location) =>
      isFullscreenLocation(location);

  /// Prefer in-body CTAs for deep shell pages so they do not stack another
  /// [Scaffold.bottomNavigationBar] above the tab bar (order detail, etc.).
  static bool preferInBodyActions(String location) {
    final path = Uri.tryParse(location)?.path ?? location;
    if (path.startsWith('/orders/') && path != '/orders') return true;
    return false;
  }

  /// Shell routes that also mount [StickyCommerceBar] above the tab bar
  /// (cart Checkout strip). Snackbars must clear both.
  static bool shellHasStickyCommerce(String location) {
    final path = Uri.tryParse(location)?.path ?? location;
    return path == '/cart' || path.startsWith('/cart/');
  }
}
