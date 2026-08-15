import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

/// QA-43 — Customer Mobile back semantics.
///
/// Root cause of unexpected exits: [StatefulShellRoute] tab roots have an
/// empty *root* navigator stack, so Android Back finished the Activity.
/// Nested/fullscreen routes must pop (or fall back to a parent) instead.
abstract final class BackNavigation {
  static bool isAppRoot({
    required GoRouter router,
    required int shellBranchIndex,
  }) =>
      shellBranchIndex == 0 && !router.canPop();

  /// In-branch parent when sibling routes were opened with `go()` (no stack).
  static String? parentInsideBranch(String path) {
    if (path.startsWith('/orders/') && path != '/orders') return '/orders';
    if (path.startsWith('/account/') && path != '/account') {
      if (path.startsWith('/account/addresses')) return null;
      return '/account';
    }
    if (path == '/wishlist' || path.startsWith('/wishlist')) return '/account';
    if (path == '/notifications' || path.startsWith('/notifications')) {
      return '/account';
    }
    if (path.startsWith('/catalog') ||
        path.startsWith('/search') ||
        path.startsWith('/offers') ||
        path.startsWith('/find-your-plant') ||
        path.startsWith('/campaigns')) {
      return '/categories';
    }
    return null;
  }

  static String fallbackFor(String path) {
    if (path.startsWith('/account/addresses')) return '/account';
    if (path == '/checkout' || path.startsWith('/checkout')) return '/cart';
    if (path.startsWith('/product/')) return '/';
    if (path.startsWith('/login') ||
        path.startsWith('/register') ||
        path.startsWith('/forgot-password') ||
        path.startsWith('/reset-password')) {
      return '/';
    }
    return parentInsideBranch(path) ?? '/';
  }

  static bool handleInterceptedBack({
    required BuildContext context,
    required StatefulNavigationShell shell,
  }) {
    final router = GoRouter.of(context);
    if (router.canPop()) {
      router.pop();
      return true;
    }
    final path = router.state.uri.path;
    final parent = parentInsideBranch(path);
    if (parent != null && parent != path) {
      context.go(parent);
      return true;
    }
    if (shell.currentIndex != 0) {
      shell.goBranch(0);
      return true;
    }
    return false;
  }

  static void toolbarBack(BuildContext context, {String? fallback}) {
    final router = GoRouter.of(context);
    if (router.canPop()) {
      router.pop();
      return;
    }
    final path = router.state.uri.path;
    context.go(fallback ?? fallbackFor(path));
  }

  static Future<void> exitApp() => SystemNavigator.pop();
}

/// Shell: intercept Android Back unless Home is the genuine app root.
class ShellBackScope extends StatelessWidget {
  const ShellBackScope({
    super.key,
    required this.navigationShell,
    required this.child,
  });

  final StatefulNavigationShell navigationShell;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: GoRouter.of(context).routerDelegate,
      builder: (context, _) {
        final router = GoRouter.of(context);
        final atRoot = BackNavigation.isAppRoot(
          router: router,
          shellBranchIndex: navigationShell.currentIndex,
        );
        return PopScope(
          canPop: atRoot,
          onPopInvokedWithResult: (didPop, _) {
            if (didPop) return;
            final consumed = BackNavigation.handleInterceptedBack(
              context: context,
              shell: navigationShell,
            );
            if (!consumed) {
              BackNavigation.exitApp();
            }
          },
          child: child,
        );
      },
    );
  }
}

/// Fullscreen flows (PDP, checkout, auth, addresses) opened via `go()`
/// have no stack — send the user to a safe parent instead of exiting.
class FullscreenBackScope extends StatelessWidget {
  const FullscreenBackScope({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: GoRouter.of(context).routerDelegate,
      builder: (context, _) {
        final router = GoRouter.of(context);
        final canPop = router.canPop();
        return PopScope(
          canPop: canPop,
          onPopInvokedWithResult: (didPop, _) {
            if (didPop) return;
            if (GoRouter.of(context).canPop()) return;
            final path = GoRouter.of(context).state.uri.path;
            context.go(BackNavigation.fallbackFor(path));
          },
          child: child,
        );
      },
    );
  }
}

class GreenLeafBackButton extends StatelessWidget {
  const GreenLeafBackButton({super.key, this.fallback});

  final String? fallback;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: 'Go back',
      icon: const Icon(Icons.arrow_back_rounded),
      onPressed: () => BackNavigation.toolbarBack(context, fallback: fallback),
    );
  }
}
