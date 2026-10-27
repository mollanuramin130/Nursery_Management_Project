import 'dart:async';

import 'package:flutter/material.dart';
import 'package:nursery_app/core/brand.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Local branded first frame; must not wait for API or other network calls.
/// No artificial startup delay by default. Native Android launch uses the same icon.
class GreenLeafSplashScreen extends StatefulWidget {
  const GreenLeafSplashScreen({
    super.key,
    this.minDisplay = Duration.zero,
    this.maxDisplay = const Duration(seconds: 2),
    required this.onFinished,
  });

  final Duration minDisplay;
  final Duration maxDisplay;
  final VoidCallback onFinished;

  @override
  State<GreenLeafSplashScreen> createState() => _GreenLeafSplashScreenState();
}

class _GreenLeafSplashScreenState extends State<GreenLeafSplashScreen> {
  Timer? _timer;
  bool _finished = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final delay = widget.minDisplay < widget.maxDisplay
          ? widget.minDisplay
          : widget.maxDisplay;
      _timer = Timer(delay.isNegative ? Duration.zero : delay, () {
        if (!mounted || _finished) return;
        _finished = true;
        widget.onFinished();
      });
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.cream,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpace.xxl),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/branding/nuvikoz-horizontal.png',
                    fit: BoxFit.contain,
                    semanticLabel: Brand.name,
                  ),
                  const SizedBox(height: AppSpace.lg),
                  Text(
                    Brand.tagline,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.primaryDeep,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
