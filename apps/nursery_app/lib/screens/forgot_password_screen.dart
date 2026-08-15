import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/core/back_navigation.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:provider/provider.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.redirectTo});

  final String? redirectTo;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final email = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _sent = false;

  static final _emailRe = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    final ok = await auth.forgotPassword(email.text.trim());
    if (!mounted) return;
    if (ok) setState(() => _sent = true);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final redirect = AuthNavigation.sanitizeRedirect(widget.redirectTo);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Forgot password'),
        leading: GreenLeafBackButton(
          fallback: AuthNavigation.loginLocation(redirect: redirect),
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
                  'Reset your password',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                const SizedBox(height: AppSpace.xs),
                Text(
                  _sent
                      ? 'If an account exists for that email, reset instructions have been sent.'
                      : 'Enter the email associated with your GreenLeaf account.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpace.xxl),
                if (_sent) ...[
                  AppSurfaceSuccess(
                    message: 'Check your email for instructions.',
                  ),
                  const SizedBox(height: AppSpace.md),
                  TextButton(
                    onPressed: () => context.push(
                      '/reset-password?email=${Uri.encodeComponent(email.text.trim())}',
                    ),
                    child: const Text('Already have a reset token?'),
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
                    label: 'Send reset link',
                    loading: auth.loading,
                    loadingLabel: 'Sending…',
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

class AppSurfaceSuccess extends StatelessWidget {
  const AppSurfaceSuccess({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpace.lg),
      decoration: BoxDecoration(
        color: AppColors.successSoft,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        border: Border.all(color: AppColors.success.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          const Icon(Icons.mark_email_read_outlined, color: AppColors.success),
          const SizedBox(width: AppSpace.md),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                color: AppColors.success,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
