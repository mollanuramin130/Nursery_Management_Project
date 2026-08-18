import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/app.dart';
import 'package:nursery_admin_mobile/core/api_client.dart';
import 'package:nursery_admin_mobile/core/back_navigation.dart';
import 'package:nursery_admin_mobile/core/session_storage.dart';
import 'package:nursery_admin_mobile/providers/auth_provider.dart';
import 'package:lottie/lottie.dart';
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
      expect(find.byType(Lottie), findsOneWidget);
      expect(find.text('GreenLeaf'), findsOneWidget);
      expect(find.text('Admin'), findsOneWidget);
      expect(find.text('Manage. Monitor. Grow.'), findsOneWidget);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 60));
      expect(finished, isTrue);
    });

    test('splash is not an API gate (policy)', () {
      final src = File('lib/screens/splash_screen.dart').readAsStringSync();
      expect(src.contains('ApiClient'), isFalse);
      expect(src.contains('never waits on API'), isTrue);
      expect(src.contains('maxDisplay'), isTrue);
    });

    testWidgets('maxDisplay prevents an indefinite hang', (tester) async {
      var finished = false;
      await tester.pumpWidget(
        MaterialApp(
          home: GreenLeafOpsSplashScreen(
            minDisplay: const Duration(seconds: 30),
            maxDisplay: const Duration(milliseconds: 40),
            onFinished: () => finished = true,
          ),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 80));
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

    test('admin adaptive mark is a shield, not the customer seedling', () {
      final xml = File(
        'android/app/src/main/res/drawable/ic_launcher_foreground.xml',
      ).readAsStringSync();
      expect(xml.contains('shield'), isTrue);
      expect(xml.contains('seedling'), isFalse);
    });
  });
}
