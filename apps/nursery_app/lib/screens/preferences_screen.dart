import 'package:flutter/material.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:nursery_app/widgets/app_feedback.dart';
import 'package:nursery_app/widgets/ui_kit.dart';
import 'package:provider/provider.dart';

class PreferencesScreen extends StatefulWidget {
  const PreferencesScreen({super.key});

  @override
  State<PreferencesScreen> createState() => _PreferencesScreenState();
}

class _PreferencesScreenState extends State<PreferencesScreen> {
  Map<String, dynamic>? _prefs;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await context.read<ApiClient>().getData(
        '/customer/preferences',
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      if (!mounted) return;
      setState(() {
        _prefs = data;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      AppFeedback.error(context, ErrorStateView.sanitize(e.toString()));
    }
  }

  Future<void> _save() async {
    if (_prefs == null || _saving) return;
    setState(() => _saving = true);
    try {
      final data = await context.read<ApiClient>().sendData(
        'PUT',
        '/customer/preferences',
        body: _prefs,
        map: (d) => Map<String, dynamic>.from(d as Map),
      );
      if (!mounted) return;
      setState(() => _prefs = data);
      AppFeedback.success(context, 'Preferences saved');
    } catch (e) {
      if (!mounted) return;
      AppFeedback.error(context, ErrorStateView.sanitize(e.toString()));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Preferences')),
        body: EmptyStateView(
          title: 'Preferences',
          message: 'Sign in to manage notification preferences.',
          actionLabel: 'Sign in',
          onAction: () => AuthNavigation.pushLogin(
            context,
            redirect: '/account/preferences',
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Preferences')),
      body: _loading || _prefs == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                SwitchListTile(
                  title: const Text('Order updates'),
                  subtitle: const Text('Shipping, delivery, returns, refunds'),
                  value: _prefs!['notify_orders'] == true,
                  onChanged: (v) =>
                      setState(() => _prefs!['notify_orders'] = v),
                ),
                SwitchListTile(
                  title: const Text('Promotions'),
                  subtitle: const Text('Campaigns and seasonal offers'),
                  value: _prefs!['notify_promotions'] == true,
                  onChanged: (v) =>
                      setState(() => _prefs!['notify_promotions'] = v),
                ),
                SwitchListTile(
                  title: const Text('Marketing emails'),
                  value: _prefs!['marketing_opt_in'] == true,
                  onChanged: (v) =>
                      setState(() => _prefs!['marketing_opt_in'] = v),
                ),
                SwitchListTile(
                  title: const Text('Email channel'),
                  value: _prefs!['notify_email'] == true,
                  onChanged: (v) =>
                      setState(() => _prefs!['notify_email'] = v),
                ),
                SwitchListTile(
                  title: const Text('Push channel'),
                  subtitle: const Text('Used when mobile push is enabled'),
                  value: _prefs!['notify_push'] == true,
                  onChanged: (v) =>
                      setState(() => _prefs!['notify_push'] = v),
                ),
                const SizedBox(height: AppSpace.lg),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save preferences'),
                ),
              ],
            ),
    );
  }
}
