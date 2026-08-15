import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/core/password_rules.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:provider/provider.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, this.redirectTo});

  final String? redirectTo;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final name = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscure = true;
  bool _obscureConfirm = true;
  bool _acceptedTerms = false;

  static final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  @override
  void initState() {
    super.initState();
    password.addListener(() => setState(() {}));
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.user != null && mounted) {
        AuthNavigation.goAfterAuth(context, redirect: widget.redirectTo);
      }
    });
  }

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    phone.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  bool get _hasMinLen => PasswordRules.hasMinLen(password.text);
  bool get _hasMixed => PasswordRules.hasMixedCase(password.text);
  bool get _hasNumber => PasswordRules.hasNumber(password.text);

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    if (!_acceptedTerms) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please accept the Terms & Privacy Policy.'),
        ),
      );
      return;
    }
    final auth = context.read<AuthProvider>();
    final cart = context.read<CartProvider>();
    final wishlist = context.read<WishlistProvider>();
    final ok = await auth.register(
      name: name.text.trim(),
      email: email.text.trim(),
      phone: phone.text.trim().isEmpty ? null : phone.text.trim(),
      password: password.text,
      passwordConfirmation: confirm.text,
    );
    if (!ok || !mounted) return;
    await cart.fetch();
    await wishlist.bootstrap(signedIn: true);
    if (!mounted) return;
    AuthNavigation.goAfterAuth(context, redirect: widget.redirectTo);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final redirect = AuthNavigation.sanitizeRedirect(widget.redirectTo);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Create account'),
        leading: GreenLeafBackButton(
          fallback: AuthNavigation.loginLocation(redirect: redirect),
        ),
      ),
      body: SafeArea(
        child: AutofillGroup(
          child: SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: EdgeInsets.fromLTRB(
              AppSpace.screen,
              AppSpace.lg,
              AppSpace.screen,
              AppSpace.screen + viewInsets.bottom,
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Create your account',
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: AppSpace.xs),
                  Text(
                    'Start growing your garden with GreenLeaf.',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpace.xxl),
                  TextFormField(
                    controller: name,
                    textCapitalization: TextCapitalization.words,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.name],
                    decoration: const InputDecoration(
                      labelText: 'Full name',
                      hintText: 'Your name',
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty) {
                        return 'Enter your full name.';
                      }
                      if (v.trim().length < 2) {
                        return 'Enter your full name.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    textCapitalization: TextCapitalization.none,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.email],
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      hintText: 'you@example.com',
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty) {
                        return 'Enter your email address.';
                      }
                      if (!_emailRe.hasMatch(v.trim())) {
                        return 'Enter a valid email address.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.telephoneNumber],
                    decoration: const InputDecoration(
                      labelText: 'Phone (optional)',
                      hintText: '10-digit mobile number',
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty) return null;
                      final digits = v.replaceAll(RegExp(r'\D'), '');
                      if (digits.length < 10) {
                        return 'Enter a valid phone number.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: password,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.newPassword],
                    decoration: InputDecoration(
                      labelText: 'Password',
                      suffixIcon: IconButton(
                        tooltip: _obscure ? 'Show password' : 'Hide password',
                        onPressed: () => setState(() => _obscure = !_obscure),
                        icon: Icon(
                          _obscure
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                    validator: PasswordRules.validate,
                  ),
                  const SizedBox(height: AppSpace.sm),
                  _RequirementRow(met: _hasMinLen, label: '8+ characters'),
                  _RequirementRow(met: _hasMixed, label: 'Upper & lower case'),
                  _RequirementRow(met: _hasNumber, label: 'At least one number'),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: confirm,
                    obscureText: _obscureConfirm,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.newPassword],
                    onFieldSubmitted: (_) {
                      if (!auth.loading) _submit();
                    },
                    decoration: InputDecoration(
                      labelText: 'Confirm password',
                      suffixIcon: IconButton(
                        tooltip: _obscureConfirm
                            ? 'Show password'
                            : 'Hide password',
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
                        icon: Icon(
                          _obscureConfirm
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                    validator: (v) {
                      if (v == null || v.isEmpty) {
                        return 'Confirm your password.';
                      }
                      if (v != password.text) {
                        return 'Passwords do not match.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppSpace.md),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: _acceptedTerms,
                    onChanged: auth.loading
                        ? null
                        : (v) => setState(() => _acceptedTerms = v ?? false),
                    title: Text(
                      'I agree to the Terms & Privacy Policy',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                  if (auth.error != null) ...[
                    const SizedBox(height: AppSpace.sm),
                    Text(
                      auth.error!,
                      style: const TextStyle(
                        color: AppColors.error,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpace.lg),
                  AppButton(
                    label: 'Create account',
                    loading: auth.loading,
                    loadingLabel: 'Creating account…',
                    expanded: true,
                    onPressed: auth.loading ? null : _submit,
                  ),
                  const SizedBox(height: AppSpace.lg),
                  TextButton(
                    onPressed: auth.loading
                        ? null
                        : () => context.go(
                            AuthNavigation.loginLocation(redirect: redirect),
                          ),
                    child: const Text('Already have an account? Sign in'),
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

class _RequirementRow extends StatelessWidget {
  const _RequirementRow({required this.met, required this.label});

  final bool met;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(
          met ? Icons.check_circle_rounded : Icons.circle_outlined,
          size: 18,
          color: met ? AppColors.success : AppColors.muted,
        ),
        const SizedBox(width: AppSpace.sm),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: met ? AppColors.success : AppColors.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
