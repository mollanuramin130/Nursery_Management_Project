import 'package:flutter/material.dart';
import 'package:nursery_app/core/support.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/retry_button.dart';
import 'package:nursery_app/widgets/support_contact_sheet.dart';

/// Branded unknown-error card. No stack traces, secrets, or technical copy.
class GreenLeafErrorView extends StatelessWidget {
  const GreenLeafErrorView({
    super.key,
    this.title = 'Something went wrong',
    this.message =
        "We're sorry, something unexpected happened while loading this part of GreenLeaf.\n\nYou can try again or contact our support team if the problem continues.",
    this.reference,
    this.onRetry,
    this.orderNumber,
    this.payment = false,
    this.compact = false,
  });

  final String title;
  final String message;
  final String? reference;
  final VoidCallback? onRetry;
  final String? orderNumber;
  final bool payment;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ref = reference ?? newErrorReference();

    return Center(
      child: Padding(
        padding: EdgeInsets.all(compact ? AppSpace.lg : AppSpace.xxl),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 400),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadii.xl),
              border: Border.all(color: AppColors.border),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x140F3D28),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpace.xl,
                AppSpace.xxl,
                AppSpace.xl,
                AppSpace.xl,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0.92, end: 1),
                    duration: const Duration(milliseconds: 420),
                    curve: Curves.easeOutCubic,
                    builder: (context, value, child) => Opacity(
                      opacity: value.clamp(0.0, 1.0),
                      child: Transform.scale(scale: value, child: child),
                    ),
                    child: Container(
                      width: 76,
                      height: 76,
                      decoration: const BoxDecoration(
                        color: AppColors.primarySoft,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.spa_rounded,
                        color: AppColors.primary,
                        size: 34,
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          color: AppColors.primaryDeep,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: AppSpace.sm),
                  Text(
                    message,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: AppColors.muted,
                          height: 1.45,
                        ),
                  ),
                  const SizedBox(height: AppSpace.md),
                  Text(
                    'Reference: $ref',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.inkSoft,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: AppSpace.xs),
                  const Text(
                    'If you contact GreenLeaf Support, please mention this reference number.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  if (onRetry != null)
                    RetryButton(
                      label: 'Try Again',
                      onPressed: onRetry,
                      expanded: true,
                    ),
                  if (onRetry != null) const SizedBox(height: AppSpace.sm),
                  AppButton(
                    label: 'Contact GreenLeaf Support',
                    onPressed: () => showSupportContactSheet(
                      context,
                      reference: ref,
                      orderNumber: orderNumber,
                      payment: payment,
                    ),
                    variant: AppButtonVariant.secondary,
                    expanded: true,
                    icon: Icons.phone_outlined,
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
