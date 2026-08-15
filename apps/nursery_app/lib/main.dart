import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:nursery_app/app.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/data/catalog_repository.dart';
import 'package:nursery_app/data/mock_data_mode.dart';
import 'package:nursery_app/data/offline_local_store.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/network_status_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:provider/provider.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.assertReleaseConfiguration();

  final storage = SessionStorage();
  final local = OfflineLocalStore();
  final offline = OfflineController(mode: defaultMockDataMode());
  final api = ApiClient(storage);
  api.networkSimulation = () => offline.simulation;
  api.mockDataMode = () => offline.mode;

  final auth = AuthProvider(api, storage);
  final cart = CartProvider(api, offline: offline, local: local);
  final wishlist = WishlistProvider(api, offline: offline, local: local);
  final network = NetworkStatusProvider();
  final catalog = CatalogRepository(api: api, offline: offline, local: local);
  cart.attachCatalog(catalog);

  api.onSessionExpired = () {
    auth.clearLocalSession();
    wishlist.clearLocal();
  };
  api.onTransportFailure = network.reportFailure;
  api.onTransportSuccess = () {
    network.reportSuccess();
    offline.markRemoteOk();
  };

  // QA-38 — health recovery triggers soft sync (no full-screen reload).
  network.onReconnected = () {
    offline.notifyReconnected();
    unawaited(() async {
      offline.beginSync();
      try {
        await catalog.syncAfterReconnect();
        if (!offline.forceTransportFailure) {
          await cart.fetch(soft: true);
          await wishlist.bootstrap(
            signedIn: auth.user != null,
            silent: true,
          );
        }
        offline.endSync(success: true);
      } catch (_) {
        offline.endSync(success: false);
      }
    }());
  };

  await auth.bootstrap();
  unawaited(cart.fetch());
  unawaited(wishlist.bootstrap(signedIn: auth.user != null));

  runApp(
    MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        Provider<SessionStorage>.value(value: storage),
        Provider<CatalogRepository>.value(value: catalog),
        ChangeNotifierProvider<OfflineController>.value(value: offline),
        ChangeNotifierProvider<AuthProvider>.value(value: auth),
        ChangeNotifierProvider<CartProvider>.value(value: cart),
        ChangeNotifierProvider<WishlistProvider>.value(value: wishlist),
        ChangeNotifierProvider<NetworkStatusProvider>.value(value: network),
      ],
      child: _LifecycleHost(
        network: network,
        child: const NurseryAppHost(),
      ),
    ),
  );
}

class NurseryAppHost extends StatefulWidget {
  const NurseryAppHost({super.key});

  @override
  State<NurseryAppHost> createState() => _NurseryAppHostState();
}

class _NurseryAppHostState extends State<NurseryAppHost> {
  late GoRouter _router;
  bool _showSplash = true;
  bool? _wasSignedIn;

  @override
  void initState() {
    super.initState();
    _router = createRouter();
  }

  @override
  void dispose() {
    _router.dispose();
    super.dispose();
  }

  void _rebuildRouterAfterLogout() {
    final previous = _router;
    _router = createRouter();
    previous.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final signedIn = context.watch<AuthProvider>().isAuthenticated;
    if (_wasSignedIn == true && !signedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        setState(_rebuildRouterAfterLogout);
      });
    }
    _wasSignedIn = signedIn;

    return NurseryApp(
      router: _router,
      showSplash: _showSplash,
      onSplashFinished: () {
        if (mounted) setState(() => _showSplash = false);
      },
    );
  }
}

class _LifecycleHost extends StatefulWidget {
  const _LifecycleHost({required this.network, required this.child});

  final NetworkStatusProvider network;
  final Widget child;

  @override
  State<_LifecycleHost> createState() => _LifecycleHostState();
}

class _LifecycleHostState extends State<_LifecycleHost>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      widget.network.onAppResumed();
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
