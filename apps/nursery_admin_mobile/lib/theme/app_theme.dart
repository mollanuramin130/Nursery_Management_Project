import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Operational Admin Mobile theme — GreenLeaf brand, not customer shopping UI.
class OpsColors {
  static const brand = Color(0xFF1A5C3A);
  static const brandDark = Color(0xFF0F3D28);
  static const surface = Color(0xFFF4F6F5);
  static const card = Color(0xFFFFFFFF);
  static const ink = Color(0xFF14201A);
  static const muted = Color(0xFF5C6B62);
  static const border = Color(0xFFD5DDD8);
  static const danger = Color(0xFFB42318);
  static const warning = Color(0xFFB54708);
  static const success = Color(0xFF027A48);
}

ThemeData buildOpsTheme() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: OpsColors.brand,
      primary: OpsColors.brand,
      surface: OpsColors.surface,
    ),
    scaffoldBackgroundColor: OpsColors.surface,
  );

  return base.copyWith(
    textTheme: GoogleFonts.figtreeTextTheme(base.textTheme).apply(
      bodyColor: OpsColors.ink,
      displayColor: OpsColors.ink,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: OpsColors.brandDark,
      foregroundColor: Colors.white,
      elevation: 0,
      titleTextStyle: GoogleFonts.figtree(
        fontWeight: FontWeight.w700,
        fontSize: 18,
        color: Colors.white,
      ),
    ),
    cardTheme: CardThemeData(
      color: OpsColors.card,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: OpsColors.border),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: OpsColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: OpsColors.border),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: OpsColors.brand,
        foregroundColor: Colors.white,
        minimumSize: const Size(48, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: OpsColors.brand.withValues(alpha: 0.12),
      labelTextStyle: WidgetStatePropertyAll(
        GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w600),
      ),
    ),
  );
}

Color statusColor(String status) {
  final s = status.toUpperCase();
  if (s.contains('CANCEL') || s.contains('FAIL') || s == 'OUT_OF_STOCK') {
    return OpsColors.danger;
  }
  if (s.contains('PENDING') || s.contains('LOW') || s.contains('DRAFT')) {
    return OpsColors.warning;
  }
  if (s.contains('DELIVER') ||
      s.contains('ACTIVE') ||
      s.contains('RECEIVED') ||
      s == 'IN_STOCK' ||
      s.contains('CONFIRM')) {
    return OpsColors.success;
  }
  return OpsColors.muted;
}
