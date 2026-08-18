import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nursery_admin_mobile/core/support.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

Future<void> showSupportContactSheet(
  BuildContext context, {
  String? reference,
}) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: OpsColors.card,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: (ctx) => _AdminSupportSheet(reference: reference),
  );
}

class _AdminSupportSheet extends StatefulWidget {
  const _AdminSupportSheet({this.reference});

  final String? reference;

  @override
  State<_AdminSupportSheet> createState() => _AdminSupportSheetState();
}

class _AdminSupportSheetState extends State<_AdminSupportSheet> {
  String? _hint;
  bool _copied = false;
  bool _calling = false;

  Future<void> _call() async {
    setState(() {
      _calling = true;
      _hint = null;
    });
    final ok = await openSupportDialer();
    if (!mounted) return;
    setState(() {
      _calling = false;
      if (!ok) _hint = 'Unable to open the phone app.';
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'GreenLeaf Support',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          const Text(
            "We're here to help.",
            textAlign: TextAlign.center,
            style: TextStyle(color: OpsColors.muted),
          ),
          const SizedBox(height: 16),
          Text('Support: $kGreenLeafSupportPhone',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700)),
          if (widget.reference != null) ...[
            const SizedBox(height: 8),
            Text(
              'Reference: ${widget.reference}',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ],
          if (_hint != null) ...[
            const SizedBox(height: 8),
            Text(_hint!, textAlign: TextAlign.center),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _calling ? null : _call,
            child: Text(_calling ? 'Opening…' : 'Call Support'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            onPressed: _copy,
            child: Text(_copied ? 'Number copied' : 'Copy Number'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).maybePop(),
            child: const Text('Cancel'),
          ),
        ],
      ),
    );
  }
}
