import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/core/api_health.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:provider/provider.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, this.redirectTo});

  final String? redirectTo;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscure = true;
  String? _healthError;
  bool _healthOk = false;

  static final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.user != null && mounted) {
        AuthNavigation.goAfterAuth(context, redirect: widget.redirectTo);
      }
      _checkHealth();
    });
  }

  Future<void> _checkHealth() async {
    final r = await probeApiHealth();
    if (!mounted) return;
    setState(() {
      _healthOk = r.ok;
      _healthError = r.ok ? null : r.message;
    });
  }

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    if (!_healthOk) {
      await _checkHealth();
      if (!mounted) return;
      if (!_healthOk) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_healthError ?? 'API unavailable')),
        );
        return;
      }
    }
    final auth = context.read<AuthProvider>();
    final cart = context.read<CartProvider>();
    final wishlist = context.read<WishlistProvider>();
    final ok = await auth.login(email.text.trim(), password.text);
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
        title: const Text('Sign in'),
        leading: const GreenLeafBackButton(),
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
                  if (_healthError != null) ...[
                    Material(
                      color: const Color(0xFFFFEBEE),
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(
                          _healthError!,
                          style: const TextStyle(
                            color: Color(0xFFB71C1C),
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpace.md),
                  ],
                  const SizedBox(height: AppSpace.md),
                  Center(
                    child: Container(
                      width: 64,
                      height: 64,
                      decoration: const BoxDecoration(
                        color: AppColors.primarySoft,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.local_florist_rounded,
                        color: AppColors.primaryDeep,
                        size: 32,
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    'Welcome back',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: AppSpace.xs),
                  Text(
                    'Sign in to continue growing your garden.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpace.xxl),
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
                    controller: password,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.password],
                    onFieldSubmitted: (_) {
                      if (!auth.loading) _submit();
                    },
                    decoration: InputDecoration(
                      labelText: 'Password',
                      hintText: 'Your password',
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
                    validator: (v) {
                      if (v == null || v.isEmpty) {
                        return 'Enter your password.';
                      }
                      return null;
                    },
                  ),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: auth.loading
                          ? null
                          : () => context.push(
                              '/forgot-password${redirect != null ? '?redirect=${Uri.encodeComponent(redirect)}' : ''}',
                            ),
                      child: const Text('Forgot password?'),
                    ),
                  ),
                  if (auth.error != null) ...[
                    Text(
                      auth.error!,
                      style: const TextStyle(
                        color: AppColors.error,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: AppSpace.md),
                  ],
                  AppButton(
                    label: 'Sign in',
                    loading: auth.loading,
                    loadingLabel: 'Signing in…',
                    expanded: true,
                    onPressed: auth.loading ? null : _submit,
                  ),
                  const SizedBox(height: AppSpace.xl),
                  Row(
                    children: [
                      const Expanded(child: Divider()),
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: AppSpace.md,
                        ),
                        child: Text(
                          'OR',
                          style: Theme.of(context).textTheme.labelMedium,
                        ),
                      ),
                      const Expanded(child: Divider()),
                    ],
                  ),
                  const SizedBox(height: AppSpace.xl),
                  Text(
                    'New to GreenLeaf?',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpace.md),
                  AppButton(
                    label: 'Create account',
                    variant: AppButtonVariant.secondary,
                    expanded: true,
                    onPressed: auth.loading
                        ? null
                        : () => context.push(
                            AuthNavigation.registerLocation(redirect: redirect),
                          ),
                  ),
                  const SizedBox(height: AppSpace.sm),
                  TextButton(
                    onPressed: auth.loading
                        ? null
                        : () => BackNavigation.toolbarBack(context),
                    child: const Text('Continue as guest'),
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
