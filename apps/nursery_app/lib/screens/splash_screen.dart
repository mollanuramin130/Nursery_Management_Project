import 'package:flutter/material.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/brand_mark.dart';

/// Branded overlay shown after the native Android launch screen.
///
/// Local UI only — must not wait for API, cart, or catalog. Home/shell
/// already paint cache → mock → API underneath.
class GreenLeafSplashScreen extends StatefulWidget {
  const GreenLeafSplashScreen({
    super.key,
    this.minDisplay = const Duration(milliseconds: 700),
    required this.onFinished,
  });

  final Duration minDisplay;
  final VoidCallback onFinished;

  @override
  State<GreenLeafSplashScreen> createState() => _GreenLeafSplashScreenState();
}

class _GreenLeafSplashScreenState extends State<GreenLeafSplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _fade;
  late final Animation<double> _scale;
  late final Animation<double> _textFade;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 520),
    );
    _fade = CurvedAnimation(parent: _ctrl, curve: Curves.easeOut);
    _scale = Tween<double>(begin: 0.92, end: 1).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOutCubic),
    );
    _textFade = CurvedAnimation(
      parent: _ctrl,
      curve: const Interval(0.28, 1, curve: Curves.easeOut),
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
      color: AppColors.cream,
      child: SafeArea(
        child: AnimatedBuilder(
          animation: _ctrl,
          builder: (context, _) {
            return Column(
              children: [
                const Spacer(flex: 2),
                Opacity(
                  opacity: _fade.value,
                  child: Transform.scale(
                    scale: _scale.value,
                    child: const GreenLeafBrandMark(size: 96),
                  ),
                ),
                const SizedBox(height: 20),
                Opacity(
                  opacity: _textFade.value,
                  child: Column(
                    children: [
                      Text(
                        'GreenLeaf',
                        style: Theme.of(context).textTheme.headlineMedium
                            ?.copyWith(
                              color: AppColors.primaryDeep,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.4,
                            ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Nursery • Grow Better',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: AppColors.secondary,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.6,
                            ),
                      ),
                    ],
                  ),
                ),
                const Spacer(flex: 3),
              ],
            );
          },
        ),
      ),
    );
  }
}
