import 'package:flutter/material.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

/// Local branded overlay — never waits on API.
class GreenLeafOpsSplashScreen extends StatefulWidget {
  const GreenLeafOpsSplashScreen({
    super.key,
    this.minDisplay = const Duration(milliseconds: 650),
    required this.onFinished,
  });

  final Duration minDisplay;
  final VoidCallback onFinished;

  @override
  State<GreenLeafOpsSplashScreen> createState() =>
      _GreenLeafOpsSplashScreenState();
}

class _GreenLeafOpsSplashScreenState extends State<GreenLeafOpsSplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _fade;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 480),
    );
    _fade = CurvedAnimation(parent: _ctrl, curve: Curves.easeOut);
    _scale = Tween<double>(begin: 0.94, end: 1).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOutCubic),
    );
    _ctrl.forward();
    Future<void>.delayed(widget.minDisplay, () {
      if (mounted) widget.onFinished();
    });
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: OpsColors.brandDark,
      child: SafeArea(
        child: AnimatedBuilder(
          animation: _ctrl,
          builder: (context, _) {
            return Opacity(
              opacity: _fade.value,
              child: Transform.scale(
                scale: _scale.value,
                child: const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      GreenLeafOpsMark(size: 88),
                      SizedBox(height: 16),
                      Text(
                        'GreenLeaf',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.3,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Operations',
                        style: TextStyle(
                          color: Color(0xFFB7C9BE),
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 1.6,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class GreenLeafOpsMark extends StatelessWidget {
  const GreenLeafOpsMark({super.key, this.size = 88});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'GreenLeaf Operations',
      child: SizedBox(
        width: size,
        height: size,
        child: DecoratedBox(
          decoration: const BoxDecoration(
            color: Color(0xFF1A5C3A),
            shape: BoxShape.circle,
          ),
          child: CustomPaint(painter: _OpsLeafPainter()),
        ),
      ),
    );
  }
}

class _OpsLeafPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFE8F2EB)
      ..style = PaintingStyle.fill
      ..isAntiAlias = true;
    final w = size.width;
    final h = size.height;
    final back = Paint()
      ..color = const Color(0xFFC5DCCB)
      ..style = PaintingStyle.fill
      ..isAntiAlias = true;
    final left = Path()
      ..moveTo(w * 0.46, h * 0.40)
      ..cubicTo(w * 0.28, h * 0.36, w * 0.22, h * 0.52, w * 0.34, h * 0.64)
      ..cubicTo(w * 0.40, h * 0.70, w * 0.48, h * 0.66, w * 0.50, h * 0.56)
      ..cubicTo(w * 0.52, h * 0.46, w * 0.52, h * 0.42, w * 0.46, h * 0.40)
      ..close();
    canvas.drawPath(left, back);
    final path = Path()
      ..moveTo(w * 0.54, h * 0.30)
      ..cubicTo(w * 0.74, h * 0.34, w * 0.80, h * 0.54, w * 0.62, h * 0.70)
      ..cubicTo(w * 0.52, h * 0.78, w * 0.42, h * 0.70, w * 0.42, h * 0.54)
      ..cubicTo(w * 0.42, h * 0.40, w * 0.46, h * 0.30, w * 0.54, h * 0.30)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
