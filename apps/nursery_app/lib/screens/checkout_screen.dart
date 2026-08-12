import 'dart:math';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/services/razorpay_checkout.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/skeletons.dart';
import 'package:nursery_app/widgets/sticky_commerce_bar.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

enum _CheckoutPhase { idle, refreshing, placing, paying, finishing }

/// Mobile-first checkout: Address → Delivery → Payment → Review.
/// Totals always come from `POST /checkout/preview`. Submission is single-flight.
class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  static const _steps = ['Address', 'Delivery', 'Payment', 'Review'];

  int step = 0;
  bool loading = true;
  String? error;
  _CheckoutPhase phase = _CheckoutPhase.idle;

  /// Once an order id is known, never place again in this session.
  int? _placedOrderId;
  String? _placedOrderNumber;
  bool _submissionLocked = false;

  List<Address> addresses = [];
  List<ShippingMethod> methods = [];
  int? addressId;
  int? shippingMethodId;
  String paymentMethod = 'cod';
  final notesCtrl = TextEditingController();

  Map<String, dynamic>? preview;
  bool previewLoading = false;
  String? previewError;
  String? _previewCoupon;

  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _line1 = TextEditingController();
  final _line2 = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _postal = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool showAddressForm = false;

  bool get _busy => phase != _CheckoutPhase.idle;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    notesCtrl.dispose();
    _name.dispose();
    _phone.dispose();
    _line1.dispose();
    _line2.dispose();
    _city.dispose();
    _state.dispose();
    _postal.dispose();
    super.dispose();
  }

  String _newRequestId() {
    final r = Random();
    return 'gl_${DateTime.now().microsecondsSinceEpoch}_${r.nextInt(1 << 32)}';
  }

  Future<void> _bootstrap() async {
    final auth = context.read<AuthProvider>();
    final cartProvider = context.read<CartProvider>();
    final api = context.read<ApiClient>();

    if (auth.user == null) {
      setState(() {
        loading = false;
        error = 'login';
      });
      return;
    }

    setState(() {
      loading = true;
      error = null;
    });

    try {
      await cartProvider.fetch();
      if (!mounted) return;
      if (cartProvider.cart.items.isEmpty) {
        setState(() {
          loading = false;
          error = 'empty';
        });
        return;
      }

      final addrs = await api.getData(
        '/customer/addresses',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => Address.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
      final ship = await api.getData(
        '/shipping/methods',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => ShippingMethod.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
      if (!mounted) return;

      Address? preferred;
      for (final a in addrs) {
        if (a.isDefault) {
          preferred = a;
          break;
        }
      }
      preferred ??= addrs.isEmpty ? null : addrs.first;

      setState(() {
        addresses = addrs;
        methods = ship;
        addressId = preferred?.id;
        shippingMethodId = ship.isEmpty ? null : ship.first.id;
        showAddressForm = addrs.isEmpty;
        loading = false;
      });
      await _refreshPreview(force: true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        error = e.toString();
      });
    }
  }

  Future<void> _refreshPreview({bool force = false}) async {
    if (addressId == null || shippingMethodId == null) {
      setState(() {
        preview = null;
        previewError = null;
      });
      return;
    }
    if (previewLoading && !force) return;

    setState(() {
      previewLoading = true;
      previewError = null;
    });

    try {
      // Always re-fetch cart so coupon / lines match server before preview.
      await context.read<CartProvider>().fetch();
      if (!mounted) return;
      final cart = context.read<CartProvider>().cart;
      if (cart.items.isEmpty) {
        setState(() {
          previewLoading = false;
          error = 'empty';
        });
        return;
      }

      final coupon = cart.couponCode;
      final data = await context.read<ApiClient>().sendData(
        'POST',
        '/checkout/preview',
        body: {
          'address_id': addressId,
          'shipping_method_id': shippingMethodId,
          if (coupon != null && coupon.isNotEmpty) 'coupon_code': coupon,
        },
        map: (data) => Map<String, dynamic>.from(data as Map),
      );
      if (!mounted) return;

      final previewCoupon = data['coupon_code']?.toString();
      // Coupon mismatch: cart had a code but preview dropped it.
      if (coupon != null &&
          coupon.isNotEmpty &&
          (previewCoupon == null || previewCoupon.isEmpty)) {
        setState(() {
          preview = data;
          _previewCoupon = previewCoupon;
          previewLoading = false;
          previewError =
              'Coupon “$coupon” is no longer valid for this order. Totals below exclude it.';
        });
        return;
      }

      setState(() {
        preview = data;
        _previewCoupon = previewCoupon;
        previewLoading = false;
        previewError = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        previewLoading = false;
        previewError = ErrorStateView.sanitize(e.toString());
        preview = null;
      });
    }
  }

  Future<void> _saveAddress() async {
    if (_busy || _submissionLocked) return;
    if (!_formKey.currentState!.validate()) return;

    setState(() => phase = _CheckoutPhase.refreshing);
    try {
      final api = context.read<ApiClient>();
      await api.sendData(
        'POST',
        '/customer/addresses',
        body: {
          'name': _name.text.trim(),
          'phone': _phone.text.trim(),
          'line1': _line1.text.trim(),
          if (_line2.text.trim().isNotEmpty) 'line2': _line2.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'postal_code': _postal.text.trim(),
          'country': 'IN',
          'is_default': addresses.isEmpty,
        },
        map: (_) => null,
      );
      if (!mounted) return;
      setState(() {
        showAddressForm = false;
        phase = _CheckoutPhase.idle;
      });
      await _bootstrap();
      if (!mounted) return;
      AppFeedback.success(context, 'Address saved');
    } catch (e) {
      if (!mounted) return;
      setState(() => phase = _CheckoutPhase.idle);
      AppFeedback.error(context, ErrorStateView.sanitize(e.toString()));
    }
  }

  Future<void> _placeOrder() async {
    if (_submissionLocked || _busy) return;
    if (addressId == null || shippingMethodId == null) {
      setState(() => step = 0);
      AppFeedback.error(context, 'Select address and delivery method');
      return;
    }

    setState(() => phase = _CheckoutPhase.refreshing);

    // Fresh cart + preview immediately before create (source of truth).
    await _refreshPreview(force: true);
    if (!mounted) return;

    final cart = context.read<CartProvider>().cart;
    if (cart.items.isEmpty) {
      setState(() {
        phase = _CheckoutPhase.idle;
        error = 'empty';
      });
      return;
    }
    if (preview == null || preview!['grand_total'] == null) {
      setState(() => phase = _CheckoutPhase.idle);
      AppFeedback.error(
        context,
        previewError ?? 'Unable to confirm totals. Please try again.',
      );
      return;
    }

    // Lock before network create to prevent double tap / double order.
    _submissionLocked = true;
    final requestId = _newRequestId();
    final couponForOrder = _previewCoupon ?? cart.couponCode;

    setState(() => phase = _CheckoutPhase.placing);

    final api = context.read<ApiClient>();
    final cartProvider = context.read<CartProvider>();

    try {
      final order = await api.sendData(
        'POST',
        '/orders',
        headers: {'X-Request-Id': requestId},
        body: {
          'address_id': addressId,
          'shipping_method_id': shippingMethodId,
          'payment_method': paymentMethod,
          if (couponForOrder != null && couponForOrder.isNotEmpty)
            'coupon_code': couponForOrder,
          if (notesCtrl.text.trim().isNotEmpty) 'notes': notesCtrl.text.trim(),
        },
        map: (data) => Map<String, dynamic>.from(data as Map),
      );

      _placedOrderId = order['id'] as int?;
      _placedOrderNumber = order['order_number']?.toString();

      if (paymentMethod == 'razorpay') {
        if (!AppConfig.onlinePaymentsEnabled) {
          await cartProvider.fetch();
          if (!mounted) return;
          setState(() => phase = _CheckoutPhase.finishing);
          context.go(
            '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}&pay=pending',
          );
          return;
        }
        setState(() => phase = _CheckoutPhase.paying);
        try {
          final pay = await api.sendData(
            'POST',
            '/payments/initiate',
            headers: {'X-Request-Id': '${requestId}_pay'},
            body: {'order_id': order['id'], 'method': 'razorpay'},
            map: (data) => Map<String, dynamic>.from(data as Map),
          );
          final clientPayload = Map<String, dynamic>.from(
            (pay['client_payload'] as Map?) ?? {},
          );
          final gateway = await openRazorpayCheckout(
            clientPayload: {
              ...clientPayload,
              'order_id':
                  pay['provider_order_id']?.toString() ??
                  clientPayload['order_id'],
            },
            description: 'Order ${order['order_number']}',
          );
          if (gateway.paymentId.isEmpty || gateway.signature.isEmpty) {
            throw RazorpayCheckoutFailed('Incomplete payment response');
          }
          final verified = await api.sendData(
            'POST',
            '/payments/verify',
            headers: {'X-Request-Id': '${requestId}_verify'},
            body: {
              'payment_id': pay['payment_id'],
              'provider_order_id': gateway.orderId,
              'provider_payment_id': gateway.paymentId,
              'provider_signature': gateway.signature,
            },
            map: (data) => Map<String, dynamic>.from(data as Map),
          );
          final paymentStatus = verified['payment_status']?.toString();
          final orderStatus = verified['order_status']?.toString();
          if (paymentStatus != 'success' || orderStatus != 'CONFIRMED') {
            await cartProvider.fetch();
            if (!mounted) return;
            setState(() => phase = _CheckoutPhase.finishing);
            context.go(
              '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}&pay=pending',
            );
            return;
          }
          setState(() => phase = _CheckoutPhase.finishing);
          await cartProvider.fetch();
          if (!mounted) return;
          context.go(
            '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}&paid=1',
          );
          return;
        } on RazorpayCheckoutCancelled {
          await cartProvider.fetch();
          if (!mounted) return;
          AppFeedback.info(context, 'Payment cancelled. Cart is unchanged.');
          setState(() => phase = _CheckoutPhase.finishing);
          context.go(
            '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}&pay=pending',
          );
          return;
        } catch (e) {
          await cartProvider.fetch();
          if (!mounted) return;
          AppFeedback.error(
            context,
            e is RazorpayCheckoutFailed
                ? e.message
                : (e is ApiException ? e.message : 'Payment could not be completed'),
          );
          setState(() => phase = _CheckoutPhase.finishing);
          context.go(
            '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}&pay=pending',
          );
          return;
        }
      }

      setState(() => phase = _CheckoutPhase.finishing);
      await cartProvider.fetch();
      if (!mounted) return;
      context.go(
        '/orders/${order['id']}?placed=${Uri.encodeQueryComponent(order['order_number'].toString())}',
      );
    } catch (e) {
      if (!mounted) return;
      // Unlock only if we never got an order id (safe to retry).
      if (_placedOrderId == null) {
        _submissionLocked = false;
        setState(() => phase = _CheckoutPhase.idle);
        AppFeedback.error(context, ErrorStateView.sanitize(e.toString()));
      } else {
        await cartProvider.fetch();
        if (!mounted) return;
        context.go(
          '/orders/$_placedOrderId?placed=${Uri.encodeQueryComponent(_placedOrderNumber ?? '')}',
        );
      }
    }
  }

  Future<void> _onPrimary() async {
    if (_busy || _submissionLocked) return;

    if (step == 0) {
      if (addressId == null) {
        AppFeedback.error(context, 'Select or add a delivery address');
        return;
      }
      setState(() => step = 1);
      await _refreshPreview(force: true);
      return;
    }
    if (step == 1) {
      if (shippingMethodId == null) {
        AppFeedback.error(context, 'Select a delivery method');
        return;
      }
      setState(() => step = 2);
      return;
    }
    if (step == 2) {
      setState(() => step = 3);
      await _refreshPreview(force: true);
      return;
    }
    await _placeOrder();
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>().cart;

    if (loading) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Checkout'),
          leading: IconButton(
            tooltip: 'Go back',
            onPressed: () => context.pop(),
            icon: const Icon(Icons.arrow_back_rounded),
          ),
        ),
        body: const SafeArea(child: CartSkeleton()),
      );
    }

    if (error == 'login') {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: EmptyStateView(
          title: 'Sign in to checkout',
          message:
              'Your cart stays with you — login to choose address and payment.',
          actionLabel: 'Sign in',
          onAction: () =>
              AuthNavigation.pushLogin(context, redirect: '/checkout'),
        ),
      );
    }

    if (error == 'empty') {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: EmptyStateView(
          title: 'Your cart is empty',
          message: 'Add plants before checking out.',
          actionLabel: 'Explore plants',
          onAction: () => context.push('/catalog'),
        ),
      );
    }

    if (error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: ErrorStateView(
          title: 'Unable to load checkout',
          message: ErrorStateView.sanitize(error),
          onRetry: _bootstrap,
        ),
      );
    }

    final payable = (preview?['grand_total'] as num?)?.toDouble();
    final canPlace =
        preview != null &&
        payable != null &&
        !previewLoading &&
        !_submissionLocked;
    final keyboardInset = MediaQuery.viewInsetsOf(context).bottom;
    final blockPop = _busy || _submissionLocked;

    return PopScope(
      canPop: !blockPop,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop || blockPop) return;
        if (step > 0) setState(() => step -= 1);
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Checkout'),
          leading: IconButton(
            tooltip: 'Go back',
            onPressed: _busy
                ? null
                : () {
                    if (step > 0) {
                      setState(() => step -= 1);
                    } else {
                      context.pop();
                    }
                  },
            icon: const Icon(Icons.arrow_back_rounded),
          ),
        ),
        body: Stack(
          children: [
            ListView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(
                AppSpace.screen,
                AppSpace.sm,
                AppSpace.screen,
                140 + keyboardInset,
              ),
              children: [
                _StepHeader(step: step, labels: _steps),
                const SizedBox(height: AppSpace.lg),
                AnimatedSwitcher(
                  duration: AppDuration.normal,
                  child: KeyedSubtree(
                    key: ValueKey(step),
                    child: switch (step) {
                      0 => _buildAddressStep(),
                      1 => _buildDeliveryStep(),
                      2 => _buildPaymentStep(),
                      _ => _buildReviewStep(cart),
                    },
                  ),
                ),
                const SizedBox(height: AppSpace.xl),
                if (previewError != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpace.md),
                    child: AppSurfaceCard(
                      padding: const EdgeInsets.all(AppSpace.md),
                      child: Text(
                        previewError!,
                        style: const TextStyle(
                          color: AppColors.warning,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                _previewTotals(),
                if (previewLoading)
                  const Padding(
                    padding: EdgeInsets.only(top: AppSpace.md),
                    child: LinearProgressIndicator(minHeight: 2),
                  ),
              ],
            ),
            if (_busy) _ProcessingOverlay(phase: phase),
          ],
        ),
        bottomNavigationBar: StickyCommerceBar(
          priceLabel: previewLoading
              ? 'Updating totals…'
              : (preview == null ? 'Confirm steps for total' : 'Payable'),
          price: payable != null ? money(payable) : '—',
          secondaryLabel: step > 0 && !_busy ? 'Back' : null,
          onSecondary: _busy
              ? null
              : () => setState(() => step = (step - 1).clamp(0, 3)),
          primaryLabel: step < 3
              ? 'Continue'
              : paymentMethod == 'cod'
              ? 'Place order'
              : 'Pay & place',
          onPrimary: () {
            if (step < 3) {
              _onPrimary();
            } else if (canPlace) {
              _onPrimary();
            }
          },
          loading: _busy,
          enabled: step < 3 ? !_busy : canPlace,
        ),
      ),
    );
  }

  Widget _buildAddressStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Delivery address',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: AppSpace.xs),
        Text(
          'Where should we send your plants?',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpace.md),
        if (addresses.isEmpty && !showAddressForm)
          const Text('No saved addresses yet.'),
        ...addresses.map((a) {
          final selected = addressId == a.id;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpace.sm),
            child: AppSurfaceCard(
              selected: selected,
              onTap: _busy
                  ? null
                  : () async {
                      setState(() => addressId = a.id);
                      await _refreshPreview(force: true);
                    },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          a.name,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      if (a.isDefault)
                        const AppBadge(
                          label: 'Default',
                          tone: AppBadgeTone.brand,
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpace.xs),
                  Text('${a.line1}${a.line2 != null ? ', ${a.line2}' : ''}'),
                  Text('${a.city}, ${a.state} ${a.postalCode}'),
                  Text(a.phone, style: const TextStyle(color: AppColors.muted)),
                ],
              ),
            ),
          );
        }),
        TextButton.icon(
          onPressed: _busy
              ? null
              : () => setState(() => showAddressForm = !showAddressForm),
          icon: Icon(showAddressForm ? Icons.close : Icons.add),
          label: Text(showAddressForm ? 'Cancel' : 'Add address'),
        ),
        if (showAddressForm)
          Form(
            key: _formKey,
            child: Column(
              children: [
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'Full name'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'Enter name' : null,
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _phone,
                  decoration: const InputDecoration(labelText: 'Phone'),
                  keyboardType: TextInputType.phone,
                  validator: (v) => (v == null || v.trim().length < 10)
                      ? 'Enter phone'
                      : null,
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _line1,
                  decoration: const InputDecoration(
                    labelText: 'Address line 1',
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'Enter address' : null,
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _line2,
                  decoration: const InputDecoration(
                    labelText: 'Address line 2 (optional)',
                  ),
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _city,
                  decoration: const InputDecoration(labelText: 'City'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'Enter city' : null,
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _state,
                  decoration: const InputDecoration(labelText: 'State'),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'Enter state' : null,
                ),
                const SizedBox(height: AppSpace.sm),
                TextFormField(
                  controller: _postal,
                  decoration: const InputDecoration(labelText: 'PIN code'),
                  keyboardType: TextInputType.number,
                  validator: (v) =>
                      (v == null || v.trim().length < 6) ? 'Enter PIN' : null,
                ),
                const SizedBox(height: AppSpace.md),
                AppButton(
                  label: 'Save address',
                  loading: phase == _CheckoutPhase.refreshing,
                  expanded: true,
                  onPressed: _busy ? null : _saveAddress,
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _buildDeliveryStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Delivery method',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: AppSpace.xs),
        Text(
          'Choose how your order arrives.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpace.md),
        ...methods.map((m) {
          final selected = shippingMethodId == m.id;
          final previewShip = (preview?['shipping_method'] as Map?)?['name']
              ?.toString();
          final etaMin = (preview?['shipping_method'] as Map?)?['eta_min_days'];
          final etaMax = (preview?['shipping_method'] as Map?)?['eta_max_days'];
          final showEta = selected && etaMin != null && etaMax != null;
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpace.sm),
            child: AppSurfaceCard(
              selected: selected,
              onTap: _busy
                  ? null
                  : () async {
                      setState(() => shippingMethodId = m.id);
                      await _refreshPreview(force: true);
                    },
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          m.name,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Text(
                          m.price <= 0 ? 'Free' : money(m.price),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        if (showEta)
                          Text(
                            'ETA $etaMin–$etaMax days'
                            '${previewShip != null ? '' : ''}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                      ],
                    ),
                  ),
                  Icon(
                    selected
                        ? Icons.radio_button_checked
                        : Icons.radio_button_off,
                    color: selected ? AppColors.primary : AppColors.muted,
                  ),
                ],
              ),
            ),
          );
        }),
      ],
    );
  }

  Widget _buildPaymentStep() {
    Widget option({
      required String value,
      required String title,
      required String subtitle,
    }) {
      final selected = paymentMethod == value;
      return Padding(
        padding: const EdgeInsets.only(bottom: AppSpace.sm),
        child: AppSurfaceCard(
          selected: selected,
          onTap: _busy ? null : () => setState(() => paymentMethod = value),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: selected ? AppColors.primary : AppColors.muted,
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Payment', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: AppSpace.xs),
        Text(
          'How would you like to pay?',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpace.md),
        option(
          value: 'cod',
          title: 'Cash on delivery',
          subtitle: 'Pay when your plants arrive — no online charge now',
        ),
        if (AppConfig.onlinePaymentsEnabled)
          option(
            value: 'razorpay',
            title: 'Pay online',
            subtitle: 'Secure card / UPI checkout',
          )
        else
          AppSurfaceCard(
            padding: const EdgeInsets.all(AppSpace.md),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.info_outline, color: AppColors.muted),
                const SizedBox(width: AppSpace.sm),
                Expanded(
                  child: Text(
                    'Online payment will appear here once Razorpay is configured for this build.',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: AppSpace.md),
        TextField(
          controller: notesCtrl,
          enabled: !_busy,
          maxLines: 2,
          textInputAction: TextInputAction.done,
          decoration: const InputDecoration(
            labelText: 'Order notes (optional)',
            hintText: 'Gate code, preferred delivery window…',
          ),
        ),
      ],
    );
  }

  Widget _buildReviewStep(Cart cart) {
    Address? addr;
    for (final a in addresses) {
      if (a.id == addressId) {
        addr = a;
        break;
      }
    }
    ShippingMethod? method;
    for (final m in methods) {
      if (m.id == shippingMethodId) {
        method = m;
        break;
      }
    }

    final previewItems = (preview?['items'] as List?) ?? const [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Review order', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: AppSpace.xs),
        Text(
          'Confirm details before placing. Prices below are from the nursery.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpace.md),
        if (addr != null) ...[
          const Text('Ship to', style: TextStyle(fontWeight: FontWeight.w700)),
          Text('${addr.name} · ${addr.phone}'),
          Text('${addr.line1}, ${addr.city} ${addr.postalCode}'),
          const SizedBox(height: AppSpace.md),
        ],
        if (method != null) ...[
          const Text('Delivery', style: TextStyle(fontWeight: FontWeight.w700)),
          Text(
            '${method.name} · ${method.price <= 0 ? 'Free' : money(method.price)}',
          ),
          const SizedBox(height: AppSpace.md),
        ],
        Text(
          'Payment · ${paymentMethod == 'cod' ? 'Cash on delivery' : 'Online'}',
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        if (_previewCoupon != null && _previewCoupon!.isNotEmpty) ...[
          const SizedBox(height: AppSpace.sm),
          AppBadge(label: 'Coupon $_previewCoupon', tone: AppBadgeTone.success),
        ],
        const SizedBox(height: AppSpace.md),
        const Text('Items', style: TextStyle(fontWeight: FontWeight.w700)),
        const SizedBox(height: AppSpace.sm),
        if (previewItems.isNotEmpty)
          ...previewItems.whereType<Map>().map((raw) {
            final i = Map<String, dynamic>.from(raw);
            return Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Expanded(child: Text('${i['name']} × ${i['quantity']}')),
                  Text(money((i['line_total'] as num?) ?? 0)),
                ],
              ),
            );
          })
        else
          ...cart.items.map(
            (i) => Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Expanded(child: Text('${i.name} × ${i.quantity}')),
                  Text(money(i.lineTotal)),
                ],
              ),
            ),
          ),
      ],
    );
  }

  Widget _previewTotals() {
    // Backend preview only — never invent totals when preview missing.
    if (preview == null) {
      return AppSurfaceCard(
        child: Text(
          previewLoading
              ? 'Calculating totals…'
              : 'Complete address and delivery to see payable amount.',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
      );
    }

    final sub = (preview!['subtotal'] as num?)?.toDouble() ?? 0;
    final ship = (preview!['shipping_total'] as num?)?.toDouble() ?? 0;
    final disc = (preview!['discount_total'] as num?)?.toDouble() ?? 0;
    final tax = (preview!['tax_total'] as num?)?.toDouble() ?? 0;
    final grand = (preview!['grand_total'] as num?)?.toDouble() ?? 0;

    return AppSurfaceCard(
      child: Column(
        children: [
          _t('Subtotal', money(sub)),
          if (disc > 0) _t('Discount', '-${money(disc)}'),
          _t('Delivery', ship <= 0 ? 'Free' : money(ship)),
          if (tax > 0) _t('Tax', money(tax)),
          const Divider(height: 20),
          _t('Total', money(grand), bold: true),
          const SizedBox(height: AppSpace.xs),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Totals confirmed by nursery checkout',
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ),
        ],
      ),
    );
  }

  Widget _t(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
              color: bold ? AppColors.ink : AppColors.muted,
            ),
          ),
          const Spacer(),
          Text(
            value,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              fontSize: bold ? 17 : 14,
              color: bold ? AppColors.primaryDeep : AppColors.ink,
            ),
          ),
        ],
      ),
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.step, required this.labels});

  final int step;
  final List<String> labels;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: List.generate(labels.length, (i) {
        final active = i == step;
        final done = i < step;
        return Expanded(
          child: Column(
            children: [
              AnimatedContainer(
                duration: AppDuration.fast,
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: done || active
                      ? AppColors.primary
                      : AppColors.borderStrong,
                ),
                child: done
                    ? const Icon(Icons.check, size: 16, color: Colors.white)
                    : Text(
                        '${i + 1}',
                        style: TextStyle(
                          color: active ? Colors.white : AppColors.ink,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
              ),
              const SizedBox(height: 4),
              Text(
                labels[i],
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w500,
                  color: active ? AppColors.primaryDeep : AppColors.muted,
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}

class _ProcessingOverlay extends StatelessWidget {
  const _ProcessingOverlay({required this.phase});

  final _CheckoutPhase phase;

  String get _label => switch (phase) {
    _CheckoutPhase.refreshing => 'Confirming cart & totals…',
    _CheckoutPhase.placing => 'Placing your order…',
    _CheckoutPhase.paying => 'Processing payment…',
    _CheckoutPhase.finishing => 'Almost done…',
    _CheckoutPhase.idle => 'Working…',
  };

  @override
  Widget build(BuildContext context) {
    return AbsorbPointer(
      child: ColoredBox(
        color: AppColors.ink.withValues(alpha: 0.35),
        child: Center(
          child: Container(
            margin: const EdgeInsets.all(AppSpace.xxl),
            padding: const EdgeInsets.all(AppSpace.xxl),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(AppRadii.xl),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const CircularProgressIndicator(),
                const SizedBox(height: AppSpace.lg),
                Text(
                  _label,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: AppSpace.sm),
                Text(
                  'Please don’t close the app or tap again.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
