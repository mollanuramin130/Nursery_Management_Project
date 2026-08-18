import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nursery_app/core/support.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';

/// Confirmation sheet before opening the native dialer.
Future<void> showSupportContactSheet(
  BuildContext context, {
  String? reference,
  String? orderNumber,
  bool payment = false,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.xl)),
    ),
    builder: (ctx) => SupportContactSheet(
      reference: reference,
      orderNumber: orderNumber,
      payment: payment,
    ),
  );
}

class SupportContactSheet extends StatefulWidget {
  const SupportContactSheet({
    super.key,
    this.reference,
    this.orderNumber,
    this.payment = false,
  });

  final String? reference;
  final String? orderNumber;
  final bool payment;

  @override
  State<SupportContactSheet> createState() => _SupportContactSheetState();
}

class _SupportContactSheetState extends State<SupportContactSheet> {
  String? _dialerHint;
  bool _copied = false;
  bool _calling = false;

  Future<void> _call() async {
    setState(() {
      _calling = true;
      _dialerHint = null;
    });
    final ok = await openSupportDialer();
    if (!mounted) return;
    setState(() {
      _calling = false;
      if (!ok) {
        _dialerHint = 'Unable to open the phone app.';
      }
    });
  }

  Future<void> _copy() async {
    await Clipboard.setData(
      const ClipboardData(text: kGreenLeafSupportPhone),
    );
    if (!mounted) return;
    setState(() => _copied = true);
  }

  @override
  Widget build(BuildContext context) {
    final paymentNote = widget.payment
        ? 'Your payment could not be confirmed yet. Calling support does not mean the payment succeeded.'
        : "We're here to help.";

    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppSpace.xl,
        AppSpace.lg,
        AppSpace.xl,
        AppSpace.xl + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(AppRadii.full),
              ),
            ),
          ),
          const SizedBox(height: AppSpace.lg),
          const Text(
            'GreenLeaf Support',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryDeep,
            ),
          ),
          const SizedBox(height: AppSpace.sm),
          Text(
            paymentNote,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.muted, height: 1.4),
          ),
          const SizedBox(height: AppSpace.lg),
          _MetaRow(label: 'Support', value: kGreenLeafSupportPhone),
          if (widget.reference != null) ...[
            const SizedBox(height: AppSpace.sm),
            _MetaRow(label: 'Reference', value: widget.reference!),
          ],
          if (widget.orderNumber != null &&
              widget.orderNumber!.trim().isNotEmpty) ...[
            const SizedBox(height: AppSpace.sm),
            _MetaRow(label: 'Order', value: widget.orderNumber!),
          ],
          if (widget.reference != null) ...[
            const SizedBox(height: AppSpace.sm),
            const Text(
              'If you contact GreenLeaf Support, please mention this reference number.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.muted, fontSize: 12, height: 1.4),
            ),
          ],
          if (_dialerHint != null) ...[
            const SizedBox(height: AppSpace.md),
            Text(
              _dialerHint!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.warning),
            ),
          ],
          const SizedBox(height: AppSpace.xl),
          AppButton(
            label: 'Call Support',
            onPressed: _calling ? null : _call,
            loading: _calling,
            expanded: true,
            icon: Icons.phone_outlined,
          ),
          const SizedBox(height: AppSpace.sm),
          AppButton(
            label: _copied ? 'Number copied' : 'Copy Number',
            onPressed: _copy,
            variant: AppButtonVariant.secondary,
            expanded: true,
          ),
          const SizedBox(height: AppSpace.sm),
          AppButton(
            label: 'Cancel',
            onPressed: () => Navigator.of(context).maybePop(),
            variant: AppButtonVariant.tertiary,
            expanded: true,
          ),
        ],
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(
          '$label:',
          style: const TextStyle(
            color: AppColors.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(width: AppSpace.sm),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: AppColors.ink,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.4,
            ),
          ),
        ),
      ],
    );
  }
}
