import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:nursery_admin_mobile/app.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/config.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/providers/ops_providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
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

  final router = createOpsRouter(auth);

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
      child: OpsApp(router: router),
    ),
  );
}
