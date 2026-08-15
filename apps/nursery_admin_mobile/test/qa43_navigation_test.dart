import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/app.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/back_navigation.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:nursery_admin_mobile/screens/splash_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('QA-43-014 admin back navigation', () {
    test('dashboard root is the only Android-exit root', () {
      final storage = SessionStorage();
      final api = ApiClient(storage);
      final auth = AuthProvider(api, storage);
      final router = createOpsRouter(auth);
      expect(
        OpsBackNavigation.isAppRoot(router: router, shellBranchIndex: 0),
        isTrue,
      );
      expect(
        OpsBackNavigation.isAppRoot(router: router, shellBranchIndex: 1),
        isFalse,
      );
      expect(
        OpsBackNavigation.isAppRoot(router: router, shellBranchIndex: 3),
        isFalse,
      );
    });
  });

  group('QA-43 admin splash + icon', () {
    testWidgets('splash completes locally', (tester) async {
      var finished = false;
      await tester.pumpWidget(
        MaterialApp(
          home: GreenLeafOpsSplashScreen(
            minDisplay: const Duration(milliseconds: 40),
            onFinished: () => finished = true,
          ),
        ),
      );
      expect(find.byType(GreenLeafOpsMark), findsOneWidget);
      expect(find.text('GreenLeaf'), findsOneWidget);
      expect(find.text('Operations'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 60));
      expect(finished, isTrue);
    });

    test('adaptive icon is not circular', () {
      final xml = File(
        'android/app/src/main/res/mipmap-anydpi-v26/ic_launcher.xml',
      ).readAsStringSync();
      expect(xml.contains('@drawable/ic_launcher_foreground'), isTrue);
      expect(xml.contains('android:drawable="@mipmap/ic_launcher"'), isFalse);
    });

    test('density mipmaps exist', () {
      for (final d in [
        'mipmap-mdpi',
        'mipmap-hdpi',
        'mipmap-xhdpi',
        'mipmap-xxhdpi',
        'mipmap-xxxhdpi',
      ]) {
        final f = File('android/app/src/main/res/$d/ic_launcher.png');
        expect(f.existsSync(), isTrue, reason: d);
        expect(f.lengthSync(), greaterThan(200));
      }
    });
  });
}
