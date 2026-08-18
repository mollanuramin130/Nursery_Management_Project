import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/app.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/app_error_handler.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  installAppErrorHandling();
  runZonedGuarded(() {
    unawaited(_boot());
  }, (error, stack) {
    AppErrorHandler.record(error, stack, screen: 'zone');
  });
}

Future<void> _boot() async {
  AppConfig.assertReleaseConfiguration();

  final storage = SessionStorage();
  late final AuthProvider auth;
  final api = ApiClient(
    storage,
    onSessionInvalid: () async {
      await auth.clearLocalSession();
    },
  );
  auth = AuthProvider(api, storage);
  await auth.bootstrap();

  runApp(
    MultiProvider(
      providers: [
        Provider.value(value: storage),
        Provider.value(value: api),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider(create: (_) => DashboardProvider(api, auth)),
        ChangeNotifierProvider(create: (_) => OrdersProvider(api)),
        ChangeNotifierProvider(create: (_) => InventoryProvider(api)),
        ChangeNotifierProvider(create: (_) => PurchasingProvider(api)),
      ],
      child: OpsAppHost(auth: auth),
    ),
  );
}

class OpsAppHost extends StatefulWidget {
  const OpsAppHost({super.key, required this.auth});

  final AuthProvider auth;

  @override
  State<OpsAppHost> createState() => _OpsAppHostState();
}

class _OpsAppHostState extends State<OpsAppHost> {
  late GoRouter _router;
  bool _showSplash = true;
  bool? _wasSignedIn;

  @override
  void initState() {
    super.initState();
    _router = createOpsRouter(widget.auth);
    AppErrorHandler.onRebuildRequested = () {
      if (mounted) setState(() {});
    };
  }

  @override
  void dispose() {
    AppErrorHandler.onRebuildRequested = null;
    _router.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final signedIn = context.watch<AuthProvider>().isAuthenticated;
    if (_wasSignedIn == true && !signedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        final previous = _router;
        setState(() => _router = createOpsRouter(widget.auth));
        previous.dispose();
      });
    }
    _wasSignedIn = signedIn;
    return OpsApp(
      router: _router,
      showSplash: _showSplash,
      onSplashFinished: () {
        if (mounted) setState(() => _showSplash = false);
      },
    );
  }
}
