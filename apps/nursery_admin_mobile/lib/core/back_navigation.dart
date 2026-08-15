import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

/// QA-43 — Admin shell back: pop nested routes, else return to Dashboard branch,
/// else allow normal Android exit on Dashboard root.
abstract final class OpsBackNavigation {
  static bool isAppRoot({
    required GoRouter router,
    required int shellBranchIndex,
  }) =>
      shellBranchIndex == 0 && !router.canPop();

  static bool handleInterceptedBack({
    required BuildContext context,
    required StatefulNavigationShell shell,
  }) {
    final router = GoRouter.of(context);
    if (router.canPop()) {
      router.pop();
      return true;
    }
    if (shell.currentIndex != 0) {
      shell.goBranch(0);
      return true;
    }
    return false;
  }

  static bool handleFullscreenRootBack(BuildContext context) {
    final router = GoRouter.of(context);
    if (router.canPop()) {
      router.pop();
      return true;
    }
    context.go('/dashboard');
    return true;
  }

  static Future<void> exitApp() => SystemNavigator.pop();
}

class OpsShellBackScope extends StatelessWidget {
  const OpsShellBackScope({
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
        final atRoot = OpsBackNavigation.isAppRoot(
          router: router,
          shellBranchIndex: navigationShell.currentIndex,
        );
        return PopScope(
          canPop: atRoot,
          onPopInvokedWithResult: (didPop, _) {
            if (didPop) return;
            final consumed = OpsBackNavigation.handleInterceptedBack(
              context: context,
              shell: navigationShell,
            );
            if (!consumed) {
              OpsBackNavigation.exitApp();
            }
          },
          child: child,
        );
      },
    );
  }
}

class OpsFullscreenBackScope extends StatelessWidget {
  const OpsFullscreenBackScope({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: GoRouter.of(context).routerDelegate,
      builder: (context, _) {
        final canPop = GoRouter.of(context).canPop();
        return PopScope(
          canPop: canPop,
          onPopInvokedWithResult: (didPop, _) {
            if (didPop) return;
            if (GoRouter.of(context).canPop()) return;
            OpsBackNavigation.handleFullscreenRootBack(context);
          },
          child: child,
        );
      },
    );
  }
}
