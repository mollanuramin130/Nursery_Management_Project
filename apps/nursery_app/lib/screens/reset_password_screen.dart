import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/password_rules.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/screens/forgot_password_screen.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:provider/provider.dart';

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({
    super.key,
    this.email,
    this.token,
    this.redirectTo,
  });

  final String? email;
  final String? token;
  final String? redirectTo;

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  late final TextEditingController email;
  late final TextEditingController token;
  final password = TextEditingController();
  final confirm = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscure = true;
  bool _obscureConfirm = true;
  bool _done = false;

  static final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  @override
  void initState() {
    super.initState();
    email = TextEditingController(text: widget.email ?? '');
    token = TextEditingController(text: widget.token ?? '');
    password.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    email.dispose();
    token.dispose();
    password.dispose();
    confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    final ok = await auth.resetPassword(
      email: email.text.trim(),
      token: token.text.trim(),
      password: password.text,
      passwordConfirmation: confirm.text,
    );
    if (!mounted) return;
    if (ok) {
      setState(() => _done = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Password updated. You can sign in.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final redirect = AuthNavigation.sanitizeRedirect(widget.redirectTo);
    final hasPrefillToken = (widget.token ?? '').isNotEmpty;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reset password'),
        leading: IconButton(
          tooltip: 'Go back',
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go(AuthNavigation.loginLocation(redirect: redirect));
            }
          },
        ),
      ),
      body: SafeArea(
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
                  'Choose a new password',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: AppSpace.xs),
                Text(
                  PasswordRules.hint,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpace.xxl),
                if (_done) ...[
                  const AppSurfaceSuccess(
                    message: 'Your password was updated successfully.',
                  ),
                  const SizedBox(height: AppSpace.xl),
                  AppButton(
                    label: 'Back to sign in',
                    expanded: true,
                    onPressed: () => context.go(
                      AuthNavigation.loginLocation(redirect: redirect),
                    ),
                  ),
                ] else ...[
                  TextFormField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    textCapitalization: TextCapitalization.none,
                    autofillHints: const [AutofillHints.email],
                    decoration: const InputDecoration(labelText: 'Email'),
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
                    controller: token,
                    enabled: !hasPrefillToken,
                    decoration: const InputDecoration(
                      labelText: 'Reset token',
                      hintText: 'From email link if not prefilled',
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty) {
                        return 'Enter the reset token from your email.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: password,
                    obscureText: _obscure,
                    autofillHints: const [AutofillHints.newPassword],
                    decoration: InputDecoration(
                      labelText: 'New password',
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
                  _RuleRow(
                    met: PasswordRules.hasMinLen(password.text),
                    label: '8+ characters',
                  ),
                  _RuleRow(
                    met: PasswordRules.hasMixedCase(password.text),
                    label: 'Upper & lower case',
                  ),
                  _RuleRow(
                    met: PasswordRules.hasNumber(password.text),
                    label: 'At least one number',
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextFormField(
                    controller: confirm,
                    obscureText: _obscureConfirm,
                    autofillHints: const [AutofillHints.newPassword],
                    decoration: InputDecoration(
                      labelText: 'Confirm password',
                      suffixIcon: IconButton(
                        tooltip: _obscureConfirm
                            ? 'Show password'
                            : 'Hide password',
                        onPressed: () => setState(
                          () => _obscureConfirm = !_obscureConfirm,
                        ),
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
                  if (auth.error != null) ...[
                    const SizedBox(height: AppSpace.md),
                    Text(
                      auth.error!,
                      style: const TextStyle(
                        color: AppColors.error,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpace.xl),
                  AppButton(
                    label: 'Update password',
                    loading: auth.loading,
                    loadingLabel: 'Updating…',
                    expanded: true,
                    onPressed: auth.loading ? null : _submit,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _RuleRow extends StatelessWidget {
  const _RuleRow({required this.met, required this.label});

  final bool met;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Icon(
            met ? Icons.check_circle : Icons.radio_button_unchecked,
            size: 16,
            color: met ? AppColors.success : AppColors.muted,
          ),
          const SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: met ? AppColors.success : AppColors.muted,
            ),
          ),
        ],
      ),
    );
  }
}
