import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';

/// GreenLeaf leaf mark — shared by about surfaces and tests.
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
      label: 'GreenLeaf',
      child: SizedBox(
        width: size,
        height: size,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: backgroundColor,
            shape: BoxShape.circle,
          ),
          child: CustomPaint(
            painter: _LeafPainter(color: color),
          ),
        ),
      ),
    );
  }
}

class _LeafPainter extends CustomPainter {
  _LeafPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final back = Paint()
      ..color = color.withValues(alpha: 0.55)
      ..style = PaintingStyle.fill
      ..isAntiAlias = true;
    final main = Paint()
      ..color = color
      ..style = PaintingStyle.fill
      ..isAntiAlias = true;

    final left = Path()
      ..moveTo(w * 0.46, h * 0.40)
      ..cubicTo(w * 0.28, h * 0.36, w * 0.22, h * 0.52, w * 0.34, h * 0.64)
      ..cubicTo(w * 0.40, h * 0.70, w * 0.48, h * 0.66, w * 0.50, h * 0.56)
      ..cubicTo(w * 0.52, h * 0.46, w * 0.52, h * 0.42, w * 0.46, h * 0.40)
      ..close();
    final right = Path()
      ..moveTo(w * 0.54, h * 0.30)
      ..cubicTo(w * 0.74, h * 0.34, w * 0.80, h * 0.54, w * 0.62, h * 0.70)
      ..cubicTo(w * 0.52, h * 0.78, w * 0.42, h * 0.70, w * 0.42, h * 0.54)
      ..cubicTo(w * 0.42, h * 0.40, w * 0.46, h * 0.30, w * 0.54, h * 0.30)
      ..close();
    canvas.drawPath(left, back);
    canvas.drawPath(right, main);

    final stem = Paint()
      ..color = AppColors.primaryDeep.withValues(alpha: 0.45)
      ..style = PaintingStyle.stroke
      ..strokeWidth = size.width * 0.045
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(
      Offset(w * 0.50, h * 0.62),
      Offset(w * 0.50, h * 0.82),
      stem,
    );
  }

  @override
  bool shouldRepaint(covariant _LeafPainter oldDelegate) =>
      oldDelegate.color != color;
}
