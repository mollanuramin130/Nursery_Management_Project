import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:provider/provider.dart';

/// Compact Subscribe & Save block for PDP.
class SubscribeSection extends StatefulWidget {
  const SubscribeSection({super.key, required this.product});

  final ProductDetail product;

  @override
  State<SubscribeSection> createState() => _SubscribeSectionState();
}

class _SubscribeSectionState extends State<SubscribeSection> {
  late int _planId;
  late int _qty;
  bool _busy = false;
  List<Map<String, dynamic>> _addresses = [];
  int? _addressId;

  @override
  void initState() {
    super.initState();
    final plans = widget.product.subscriptionPlans;
    _planId = plans.isNotEmpty ? plans.first.id : 0;
    _qty = plans.isNotEmpty ? plans.first.quantityDefault : 1;
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadAddresses());
  }

  Future<void> _loadAddresses() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) return;
    try {
      final api = context.read<ApiClient>();
      final data = await api.getData(
        '/customer/addresses',
        map: (d) => d,
      );
      final list = data is List
          ? data
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList()
          : <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        _addresses = list;
        Map<String, dynamic>? def;
        for (final a in list) {
          if (a['is_default'] == true) {
            def = a;
            break;
          }
        }
        def ??= list.isNotEmpty ? list.first : null;
        _addressId = (def?['id'] as num?)?.toInt();
      });
    } catch (_) {}
  }

  Future<void> _subscribe() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      context.push('/login?redirect=/product/${widget.product.summary.slug}');
      return;
    }
    if (_addressId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add a delivery address first')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final api = context.read<ApiClient>();
      final data = await api.sendData(
        'POST',
        '/subscriptions',
        body: {
          'plan_id': _planId,
          'quantity': _qty,
          'address_id': _addressId,
          'payment_method': 'razorpay',
        },
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      if (!mounted) return;
      final order = data['order'];
      final sub = data['subscription'];
      if (data['payment_required'] == true && order is Map && order['id'] != null) {
        context.push('/orders/${order['id']}');
      } else if (sub is Map && sub['id'] != null) {
        context.push('/account/subscriptions/${sub['id']}');
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final plans = widget.product.subscriptionPlans;
    if (!widget.product.subscriptionEnabled || plans.isEmpty) {
      return const SizedBox.shrink();
    }
    final plan = plans.firstWhere(
      (p) => p.id == _planId,
      orElse: () => plans.first,
    );

    return Container(
      margin: const EdgeInsets.only(top: AppSpace.lg),
      padding: const EdgeInsets.all(AppSpace.md),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(AppRadii.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Subscribe & save',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          const Text(
            'Each cycle creates an order you pay for. No auto-charge.',
            style: TextStyle(fontSize: 12, color: AppColors.muted),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            value: _planId,
            decoration: const InputDecoration(labelText: 'Plan'),
            items: plans
                .map(
                  (p) => DropdownMenuItem(
                    value: p.id,
                    child: Text(
                      '${p.name} · ${p.frequency} · ₹${p.unitPrice.toStringAsFixed(0)}',
                    ),
                  ),
                )
                .toList(),
            onChanged: (v) {
              if (v == null) return;
              final p = plans.firstWhere((x) => x.id == v);
              setState(() {
                _planId = v;
                _qty = p.quantityDefault;
              });
            },
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  initialValue: '$_qty',
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'Qty'),
                  onChanged: (v) =>
                      _qty = int.tryParse(v) == null || int.parse(v) < 1
                      ? 1
                      : int.parse(v),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: DropdownButtonFormField<int>(
                  value: _addressId,
                  decoration: const InputDecoration(labelText: 'Address'),
                  items: _addresses
                      .map(
                        (a) => DropdownMenuItem(
                          value: (a['id'] as num).toInt(),
                          child: Text(
                            '${a['label'] ?? a['line1']} · ${a['city']}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => setState(() => _addressId = v),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: _busy || widget.product.summary.isOutOfStock
                  ? null
                  : _subscribe,
              child: Text(
                _busy
                    ? 'Starting…'
                    : 'Start ${plan.frequency.toLowerCase()} plan',
              ),
            ),
          ),
        ],
      ),
    );
  }
}
