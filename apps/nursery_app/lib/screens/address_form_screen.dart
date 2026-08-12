import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_messages.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class AddressFormScreen extends StatefulWidget {
  const AddressFormScreen({super.key, this.addressId});

  final int? addressId;

  bool get isEditing => addressId != null;

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _line1 = TextEditingController();
  final _line2 = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _postal = TextEditingController();

  String _label = 'Home';
  bool _isDefault = false;
  bool _loading = true;
  bool _saving = false;
  String? _loadError;

  static const _labels = ['Home', 'Office', 'Other'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _line1.dispose();
    _line2.dispose();
    _city.dispose();
    _state.dispose();
    _postal.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final user = context.read<AuthProvider>().user;
    if (user == null) {
      AuthNavigation.pushLogin(
        context,
        redirect: widget.isEditing
            ? '/account/addresses/${widget.addressId}/edit'
            : '/account/addresses/new',
      );
      return;
    }

    if (!widget.isEditing) {
      setState(() {
        _loading = false;
        _isDefault = true; // first address UX; server still authoritative
      });
      return;
    }

    try {
      final list = await context.read<ApiClient>().getData(
        '/customer/addresses',
        map: (data) => (data as List)
            .whereType<Map>()
            .map((e) => Address.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
      final match = list.where((a) => a.id == widget.addressId).toList();
      if (match.isEmpty) {
        setState(() {
          _loading = false;
          _loadError = 'Address not found.';
        });
        return;
      }
      final a = match.first;
      _name.text = a.name;
      _phone.text = a.phone;
      _line1.text = a.line1;
      _line2.text = a.line2 ?? '';
      _city.text = a.city;
      _state.text = a.state;
      _postal.text = a.postalCode;
      _label = _labels.contains(a.label) ? a.label! : 'Home';
      _isDefault = a.isDefault;
      setState(() => _loading = false);
    } catch (e) {
      setState(() {
        _loading = false;
        _loadError = AuthMessages.fromException(e);
      });
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    final body = {
      'label': _label,
      'name': _name.text.trim(),
      'phone': _phone.text.trim(),
      'line1': _line1.text.trim(),
      'line2': _line2.text.trim().isEmpty ? null : _line2.text.trim(),
      'city': _city.text.trim(),
      'state': _state.text.trim(),
      'postal_code': _postal.text.trim(),
      'country': 'IN',
      'is_default': _isDefault,
    };
    try {
      final api = context.read<ApiClient>();
      if (widget.isEditing) {
        await api.sendData(
          'PUT',
          '/customer/addresses/${widget.addressId}',
          body: body,
          map: (_) => null,
        );
      } else {
        await api.sendData(
          'POST',
          '/customer/addresses',
          body: body,
          map: (_) => null,
        );
      }
      if (!mounted) return;
      AppFeedback.success(context, 'Address saved');
      context.pop(true);
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, AuthMessages.fromException(e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final viewInsets = MediaQuery.viewInsetsOf(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.isEditing ? 'Edit address' : 'Add new address'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null
          ? ErrorStateView(
              title: 'Unable to load address',
              message: _loadError!,
              onRetry: () {
                setState(() {
                  _loading = true;
                  _loadError = null;
                });
                _bootstrap();
              },
            )
          : SafeArea(
              child: AutofillGroup(
                child: SingleChildScrollView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.fromLTRB(
                    AppSpace.screen,
                    AppSpace.lg,
                    AppSpace.screen,
                    AppSpace.screen + viewInsets.bottom + 80,
                  ),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Address type',
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        const SizedBox(height: AppSpace.sm),
                        Wrap(
                          spacing: AppSpace.sm,
                          children: _labels.map((label) {
                            final selected = _label == label;
                            return ChoiceChip(
                              label: Text(label),
                              selected: selected,
                              onSelected: _saving
                                  ? null
                                  : (_) => setState(() => _label = label),
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: AppSpace.lg),
                        TextFormField(
                          controller: _name,
                          textCapitalization: TextCapitalization.words,
                          autofillHints: const [AutofillHints.name],
                          decoration: const InputDecoration(
                            labelText: 'Full name *',
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Enter your name.'
                              : null,
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _phone,
                          keyboardType: TextInputType.phone,
                          autofillHints: const [AutofillHints.telephoneNumber],
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[0-9+\-\s]'),
                            ),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Phone *',
                          ),
                          validator: (v) {
                            if (v == null || v.trim().isEmpty) {
                              return 'Enter a valid phone number.';
                            }
                            final digits = v.replaceAll(RegExp(r'\D'), '');
                            if (digits.length < 10) {
                              return 'Enter a valid phone number.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _line1,
                          textCapitalization: TextCapitalization.sentences,
                          autofillHints: const [
                            AutofillHints.streetAddressLine1,
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Address *',
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Enter your address.'
                              : null,
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _line2,
                          textCapitalization: TextCapitalization.sentences,
                          autofillHints: const [
                            AutofillHints.streetAddressLine2,
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Apartment, suite (optional)',
                          ),
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _city,
                          textCapitalization: TextCapitalization.words,
                          autofillHints: const [AutofillHints.addressCity],
                          decoration: const InputDecoration(
                            labelText: 'City *',
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Enter your city.'
                              : null,
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _state,
                          textCapitalization: TextCapitalization.words,
                          autofillHints: const [AutofillHints.addressState],
                          decoration: const InputDecoration(
                            labelText: 'State *',
                          ),
                          validator: (v) => (v == null || v.trim().isEmpty)
                              ? 'Enter your state.'
                              : null,
                        ),
                        const SizedBox(height: AppSpace.md),
                        TextFormField(
                          controller: _postal,
                          keyboardType: TextInputType.number,
                          autofillHints: const [AutofillHints.postalCode],
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                            LengthLimitingTextInputFormatter(6),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Postal code *',
                          ),
                          validator: (v) {
                            if (v == null || v.trim().isEmpty) {
                              return 'Enter a valid postal code.';
                            }
                            if (v.trim().length < 6) {
                              return 'Enter a valid postal code.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: AppSpace.md),
                        SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Use as default address'),
                          value: _isDefault,
                          onChanged: _saving
                              ? null
                              : (v) => setState(() => _isDefault = v),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
      bottomNavigationBar: _loading || _loadError != null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpace.screen,
                  AppSpace.sm,
                  AppSpace.screen,
                  AppSpace.md,
                ),
                child: AppButton(
                  label: 'Save address',
                  loading: _saving,
                  loadingLabel: 'Saving…',
                  expanded: true,
                  onPressed: _saving ? null : _save,
                ),
              ),
            ),
    );
  }
}
