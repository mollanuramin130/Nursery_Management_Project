import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Soft entrance for home/list content. Respects reduced motion.
class FadeInUp extends StatelessWidget {
  const FadeInUp({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.duration = AppDuration.normal,
    this.offset = 12,
  });

  final Widget child;
  final Duration delay;
  final Duration duration;
  final double offset;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;

    return FutureBuilder<void>(
      future: Future<void>.delayed(delay),
      builder: (context, snap) {
        if (snap.connectionState != ConnectionState.done) {
          return Opacity(opacity: 0, child: child);
        }
        return TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: duration,
          curve: Curves.easeOutCubic,
          builder: (context, t, child) {
            return Opacity(
              opacity: t,
              child: Transform.translate(
                offset: Offset(0, offset * (1 - t)),
                child: child,
              ),
            );
          },
          child: child,
        );
      },
    );
  }
}

/// Scale pulse for add-to-cart / wishlist micro-feedback.
class PulseOnTap extends StatefulWidget {
  const PulseOnTap({
    super.key,
    required this.child,
    this.onTap,
    this.enabled = true,
  });

  final Widget child;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  State<PulseOnTap> createState() => _PulseOnTapState();
}

class _PulseOnTapState extends State<PulseOnTap>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: AppDuration.fast,
    lowerBound: 0.94,
    upperBound: 1,
    value: 1,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _handle() async {
    if (!widget.enabled) return;
    final disable = MediaQuery.disableAnimationsOf(context);
    if (!disable) {
      await _controller.reverse();
      await _controller.forward();
    }
    HapticFeedback.selectionClick();
    widget.onTap?.call();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: _handle,
      child: ScaleTransition(scale: _controller, child: widget.child),
    );
  }
}

/// Animated wishlist heart (outline ↔ filled).
class WishlistHeart extends StatelessWidget {
  const WishlistHeart({
    super.key,
    required this.saved,
    required this.onPressed,
    this.size = 20,
  });

  final bool saved;
  final VoidCallback onPressed;
  final double size;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: saved ? 'Remove from wishlist' : 'Add to wishlist',
      onPressed: onPressed,
      constraints: const BoxConstraints(
        minWidth: AppTouch.iconButton,
        minHeight: AppTouch.iconButton,
      ),
      padding: const EdgeInsets.all(AppSpace.sm),
      icon: AnimatedSwitcher(
        duration: AppDuration.fast,
        transitionBuilder: (child, anim) =>
            ScaleTransition(scale: anim, child: child),
        child: Icon(
          saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
          key: ValueKey(saved),
          size: size,
          color: saved ? AppColors.sale : AppColors.primaryDeep,
        ),
      ),
    );
  }
}
