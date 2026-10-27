import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

/// NUVIKOZ leaf mark — shared by about surfaces and tests.
class GreenLeafBrandMark extends StatelessWidget {
  const GreenLeafBrandMark({
    super.key,
    this.size = 88,
    this.color = AppColors.primary,
    this.backgroundColor = AppColors.primarySoft,
  });

  final double size;
  final Color color;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'NUVIKOZ',
      child: SizedBox(
        width: size,
        height: size,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: backgroundColor,
            shape: BoxShape.circle,
          ),
          child: Image.asset(
            'assets/branding/nuvikoz-icon.png',
            fit: BoxFit.contain,
            excludeFromSemantics: true,
          ),
        ),
      ),
    );
  }
}
