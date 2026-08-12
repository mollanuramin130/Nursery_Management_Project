import 'package:flutter/material.dart';

/// Botanical design tokens — aligned with web brand, tuned for mobile.
abstract final class AppColors {
  static const primary = Color(0xFF1A5C3A);
  static const primaryHover = Color(0xFF154C30);
  static const primaryDeep = Color(0xFF0F3D28);
  static const primarySoft = Color(0xFFE8F2EB);

  static const secondary = Color(0xFF8B7355);
  static const secondarySoft = Color(0xFFF3EEE7);

  static const cream = Color(0xFFF7F4EE);
  static const background = Color(0xFFFAF9F6);
  static const surface = Color(0xFFFFFFFF);
  static const surfaceMuted = Color(0xFFF3F5F2);

  static const ink = Color(0xFF1A241C);
  static const inkSoft = Color(0xFF3D4A40);
  static const muted = Color(0xFF5A655E);

  static const border = Color(0xFFE2E8E3);
  static const borderStrong = Color(0xFFC9D4CC);

  static const success = Color(0xFF2F7D4A);
  static const successSoft = Color(0xFFE6F4EA);
  static const warning = Color(0xFFB7791F);
  static const warningSoft = Color(0xFFFEF3C7);
  static const error = Color(0xFFB42318);
  static const errorSoft = Color(0xFFFEE4E2);
  static const info = Color(0xFF175CD3);

  static const sale = Color(0xFFB42318);

  /// Darker gold for WCAG-friendly contrast on light surfaces.
  static const rating = Color(0xFFA16207);
}

/// Shared layout metrics for product grids.
abstract final class AppLayout {
  /// Tall enough for image + title + price + Add without overflow.
  static const productGridAspectRatio = 0.58;
}

abstract final class AppRadii {
  static const xs = 6.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;
  static const full = 999.0;
}

/// 8-point spacing scale.
abstract final class AppSpace {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;
  static const section = 32.0;
  static const huge = 40.0;
  static const massive = 48.0;

  /// Standard horizontal page padding.
  static const screen = 16.0;
}

abstract final class AppShadows {
  static List<BoxShadow> get sm => [
    BoxShadow(
      color: AppColors.ink.withValues(alpha: 0.04),
      blurRadius: 8,
      offset: const Offset(0, 2),
    ),
  ];

  static List<BoxShadow> get md => [
    BoxShadow(
      color: AppColors.ink.withValues(alpha: 0.08),
      blurRadius: 16,
      offset: const Offset(0, 6),
    ),
  ];

  static List<BoxShadow> get lg => [
    BoxShadow(
      color: AppColors.ink.withValues(alpha: 0.10),
      blurRadius: 24,
      offset: const Offset(0, 10),
    ),
  ];

  /// Sticky bars / bottom sheets — upward lift.
  static List<BoxShadow> get floatUp => [
    BoxShadow(
      color: AppColors.ink.withValues(alpha: 0.08),
      blurRadius: 20,
      offset: const Offset(0, -4),
    ),
  ];
}

abstract final class AppDuration {
  static const instant = Duration(milliseconds: 120);
  static const fast = Duration(milliseconds: 180);
  static const normal = Duration(milliseconds: 280);
  static const slow = Duration(milliseconds: 400);
}

abstract final class AppTouch {
  /// Material / Play accessibility minimum touch target.
  static const min = 48.0;
  static const iconButton = 48.0;
}

abstract final class AppFonts {
  static const display = 'Fraunces';
  static const body = 'Figtree';
}

/// Free-delivery display threshold (INR). Prefer API config when available.
const freeDeliveryThreshold = 999.0;
