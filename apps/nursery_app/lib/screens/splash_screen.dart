import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:lottie/lottie.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Branded overlay shown after the native Android launch screen.
///
/// Uses a trimmed Lottie (seed drop → green bloom → basket) with no phone
/// frame or third-party lettering. [GreenLeaf] wordmark is drawn in Flutter.
///
/// Local UI only — must not wait for API, cart, or catalog.
class GreenLeafSplashScreen extends StatefulWidget {
  const GreenLeafSplashScreen({
    super.key,
    this.minDisplay = const Duration(milliseconds: 3400),
    this.maxDisplay = const Duration(seconds: 6),
    required this.onFinished,
  });

  static const lottieAsset = 'assets/lottie/splash_motion.json';

  final Duration minDisplay;
  final Duration maxDisplay;
  final VoidCallback onFinished;

  @override
  State<GreenLeafSplashScreen> createState() => _GreenLeafSplashScreenState();
}

class _GreenLeafSplashScreenState extends State<GreenLeafSplashScreen>
    with TickerProviderStateMixin {
  static const _fadeOut = Duration(milliseconds: 380);

  late final AnimationController _lottie;
  late final AnimationController _exit;
  late final AnimationController _brand;

  bool _started = false;
  bool _finished = false;
  Timer? _minTimer;
  Timer? _maxTimer;

  @override
  void initState() {
    super.initState();
    _lottie = AnimationController(vsync: this);
    _exit = AnimationController(vsync: this, duration: _fadeOut);
    _brand = AnimationController(vsync: this);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || _finished) return;
      _armAfterFirstFrame();
    });
  }

  void _armAfterFirstFrame() {
    final display = _effectiveDisplay(MediaQuery.disableAnimationsOf(context));
    _minTimer = Timer(display, _beginExit);
    _maxTimer = Timer(widget.maxDisplay, _beginExit);
  }

  Duration _effectiveDisplay(bool reduceMotion) {
    if (reduceMotion &&
        widget.minDisplay >= const Duration(milliseconds: 1800)) {
      return const Duration(milliseconds: 650);
    }
    return widget.minDisplay;
  }

  Duration get _exitDuration {
    final shortest = widget.minDisplay < widget.maxDisplay
        ? widget.minDisplay
        : widget.maxDisplay;
    if (shortest < const Duration(milliseconds: 400)) {
      return Duration.zero;
    }
    return _fadeOut;
  }

  void _onLottieLoaded(LottieComposition composition) {
    _lottie.duration = composition.duration;
    _brand.duration = composition.duration;

    final reduce = MediaQuery.disableAnimationsOf(context);
    if (reduce) {
      _lottie.value = 1;
      _brand.value = 1;
    } else {
      _lottie.forward();
      _brand.forward();
    }
  }

  void _beginExit() {
    if (!mounted || _finished) return;
    if (_exitDuration == Duration.zero) {
      _finish();
      return;
    }
    if (_exit.isAnimating || _exit.status == AnimationStatus.completed) {
      return;
    }
    _exit.forward().whenComplete(_finish);
  }

  void _finish() {
    if (!mounted || _finished) return;
    _finished = true;
    widget.onFinished();
  }

  @override
  void dispose() {
    _minTimer?.cancel();
    _maxTimer?.cancel();
    _lottie.dispose();
    _exit.dispose();
    _brand.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final word = CurvedAnimation(
      parent: _brand,
      curve: const Interval(0.52, 0.78, curve: Curves.easeOutCubic),
    );
    final tag = CurvedAnimation(
      parent: _brand,
      curve: const Interval(0.68, 0.92, curve: Curves.easeOut),
    );

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: AppColors.cream,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: FadeTransition(
        opacity: Tween<double>(begin: 1, end: 0).animate(
          CurvedAnimation(parent: _exit, curve: Curves.easeOutCubic),
        ),
        child: AbsorbPointer(
          child: DecoratedBox(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0xFFFFFCF7),
                  Color(0xFFF7F4EE),
                  Color(0xFFE8F2EB),
                ],
                stops: [0.0, 0.46, 1.0],
              ),
            ),
            child: Stack(
              fit: StackFit.expand,
              children: [
                Align(
                  alignment: const Alignment(0, -0.08),
                  child: Lottie.asset(
                    GreenLeafSplashScreen.lottieAsset,
                    controller: _lottie,
                    fit: BoxFit.contain,
                    alignment: Alignment.center,
                    height: MediaQuery.sizeOf(context).height * 0.62,
                    onLoaded: _onLottieLoaded,
                  ),
                ),
                SafeArea(
                  child: Column(
                    children: [
                      const Spacer(flex: 5),
                      FadeTransition(
                        opacity: word,
                        child: SlideTransition(
                          position: Tween<Offset>(
                            begin: const Offset(0, 0.18),
                            end: Offset.zero,
                          ).animate(word),
                          child: Text(
                            'GreenLeaf',
                            textAlign: TextAlign.center,
                            style: Theme.of(context)
                                .textTheme
                                .headlineMedium
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.6,
                                  height: 1.05,
                                  shadows: const [
                                    Shadow(
                                      color: Color(0x440F3D28),
                                      blurRadius: 18,
                                      offset: Offset(0, 4),
                                    ),
                                  ],
                                ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      FadeTransition(
                        opacity: tag,
                        child: SlideTransition(
                          position: Tween<Offset>(
                            begin: const Offset(0, 0.12),
                            end: Offset.zero,
                          ).animate(tag),
                          child: Text(
                            'Grow Better. Live Greener.',
                            textAlign: TextAlign.center,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(
                                  color: const Color(0xFFF7F4EE),
                                  fontWeight: FontWeight.w600,
                                  letterSpacing: 0.2,
                                  shadows: const [
                                    Shadow(
                                      color: Color(0x330F3D28),
                                      blurRadius: 10,
                                    ),
                                  ],
                                ),
                          ),
                        ),
                      ),
                      const Spacer(flex: 3),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
