import 'package:flutter/material.dart';
import 'package:nursery_admin_mobile/core/support.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';
import 'package:nursery_admin_mobile/widgets/support_contact_sheet.dart';

class GreenLeafErrorView extends StatefulWidget {
  const GreenLeafErrorView({
    super.key,
    this.title = 'Something went wrong',
    this.message =
        'Something went wrong while loading this section. You can try again or contact support if it continues.',
    this.reference,
    this.onRetry,
    this.operational = false,
  });

  final String title;
  final String message;
  final String? reference;
  final VoidCallback? onRetry;
  final bool operational;

  @override
  State<GreenLeafErrorView> createState() => _GreenLeafErrorViewState();
}

class _GreenLeafErrorViewState extends State<GreenLeafErrorView> {
  bool _busy = false;

  Future<void> _retry() async {
    if (_busy || widget.onRetry == null) return;
    setState(() => _busy = true);
    widget.onRetry!();
    await Future<void>.delayed(const Duration(milliseconds: 450));
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final ref = widget.reference ?? newErrorReference();
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 400),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: OpsColors.card,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: OpsColors.border),
            ),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.spa_rounded, color: OpsColors.brand, size: 36),
                  const SizedBox(height: 16),
                  Text(
                    widget.title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    widget.message,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: OpsColors.muted, height: 1.4),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Reference: $ref',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: widget.onRetry == null || _busy ? null : _retry,
                    child: Text(_busy ? 'Retrying…' : 'Try Again'),
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: () =>
                        showSupportContactSheet(context, reference: ref),
                    child: const Text('Contact Support'),
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
