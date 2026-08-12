import 'dart:async';

import 'package:flutter/material.dart';
import 'package:nursery_app/app.dart';
import 'package:nursery_app/core/api_client.dart';
import 'package:nursery_app/core/config.dart';
import 'package:nursery_app/core/session_storage.dart';
import 'package:nursery_app/providers/auth_provider.dart';
import 'package:nursery_app/providers/cart_provider.dart';
import 'package:nursery_app/providers/wishlist_provider.dart';
import 'package:provider/provider.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.assertReleaseConfiguration();

  final storage = SessionStorage();
  final api = ApiClient(storage);
  final auth = AuthProvider(api, storage);
  final cart = CartProvider(api);
  final wishlist = WishlistProvider(api);

  // Auth bootstrap is required for session restore; cart/wishlist warm in background
  // so a slow/offline API cannot block first frame indefinitely.
  await auth.bootstrap();
  unawaited(cart.fetch());
  unawaited(wishlist.bootstrap(signedIn: auth.user != null));

  runApp(
    MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        Provider<SessionStorage>.value(value: storage),
        ChangeNotifierProvider<AuthProvider>.value(value: auth),
        ChangeNotifierProvider<CartProvider>.value(value: cart),
        ChangeNotifierProvider<WishlistProvider>.value(value: wishlist),
      ],
      child: NurseryApp(router: createRouter()),
    ),
  );
}
