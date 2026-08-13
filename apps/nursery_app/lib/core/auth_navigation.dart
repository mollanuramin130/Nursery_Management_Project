import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Safe return-intent helpers for login / register flows.
abstract final class AuthNavigation {
  static const _authPaths = {
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
  };

  /// Only allow in-app relative paths; block auth loops and open redirects.
  static String? sanitizeRedirect(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    var path = raw.trim();
    try {
      path = Uri.decodeComponent(path);
    } catch (_) {}
    if (!path.startsWith('/') || path.startsWith('//')) return null;
    final uri = Uri.parse(path);
    final clean = uri.path;
    if (_authPaths.any((p) => clean == p || clean.startsWith('$p/'))) {
      return null;
    }
    return uri.hasQuery ? '$clean?${uri.query}' : clean;
  }

  static String loginLocation({String? redirect}) {
    final safe = sanitizeRedirect(redirect);
    if (safe == null) return '/login';
    return '/login?redirect=${Uri.encodeComponent(safe)}';
  }

  static String registerLocation({String? redirect}) {
    final safe = sanitizeRedirect(redirect);
    if (safe == null) return '/register';
    return '/register?redirect=${Uri.encodeComponent(safe)}';
  }

  static void pushLogin(BuildContext context, {String? redirect}) {
    final target =
        sanitizeRedirect(redirect) ??
        sanitizeRedirect(GoRouterState.of(context).uri.toString()) ??
        '/account';
    context.push(loginLocation(redirect: target));
  }

  static void goAfterAuth(BuildContext context, {String? redirect}) {
    final target = sanitizeRedirect(redirect) ?? '/account';
    context.go(target);
  }
}
