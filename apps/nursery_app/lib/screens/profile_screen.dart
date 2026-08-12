import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

/// Editable name/phone via PUT /customer/profile. Email is read-only.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController();
    _phone = TextEditingController();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final user = context.read<AuthProvider>().user;
      if (user == null) {
        AuthNavigation.pushLogin(context, redirect: '/account/profile');
        return;
      }
      _name.text = user.name;
      _phone.text = user.phone ?? '';
      setState(() => _ready = true);
    });
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    final auth = context.read<AuthProvider>();
    final ok = await auth.updateProfile(
      name: _name.text.trim(),
      phone: _phone.text.trim(), // empty string clears optional phone
    );
    if (!mounted) return;
    if (ok) {
      AppFeedback.success(context, 'Profile updated');
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final viewInsets = MediaQuery.viewInsetsOf(context);

    if (auth.user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Personal information')),
        body: EmptyStateView(
          title: 'Sign in required',
          message: 'Sign in to view and update your profile.',
          actionLabel: 'Sign in',
          onAction: () =>
              AuthNavigation.pushLogin(context, redirect: '/account/profile'),
        ),
      );
    }

    if (!_ready) {
      return Scaffold(
        appBar: AppBar(title: const Text('Personal information')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Personal information')),
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
                  'Keep your details up to date for smoother deliveries.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AppSpace.xxl),
                TextFormField(
                  controller: _name,
                  textCapitalization: TextCapitalization.words,
                  autofillHints: const [AutofillHints.name],
                  decoration: const InputDecoration(labelText: 'Full name'),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) {
                      return 'Enter your full name.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpace.md),
                TextFormField(
                  initialValue: auth.user!.email,
                  enabled: false,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    helperText: 'Email cannot be changed here.',
                  ),
                ),
                const SizedBox(height: AppSpace.md),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  autofillHints: const [AutofillHints.telephoneNumber],
                  decoration: const InputDecoration(
                    labelText: 'Phone',
                    hintText: 'Optional',
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
                  label: 'Save changes',
                  loading: auth.loading,
                  loadingLabel: 'Saving…',
                  expanded: true,
                  onPressed: auth.loading ? null : _save,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
