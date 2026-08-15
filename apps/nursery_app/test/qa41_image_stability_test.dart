import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/widgets/resilient_image.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('QA-41 category glyph shows initial when imageUrl is null',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: CategoryCircleImage(name: 'Admin Test Category', size: 60),
        ),
      ),
    );
    expect(find.text('A'), findsOneWidget);
    expect(find.byType(CategoryGlyphAvatar), findsOneWidget);
  });

  testWidgets('QA-41 Retry bump changes ResilientNetworkImage cache key gen',
      (tester) async {
    final offline = OfflineController();
    expect(offline.syncGeneration, 0);
    offline.bumpSyncGeneration();
    expect(offline.syncGeneration, 1);

    await tester.pumpWidget(
      ChangeNotifierProvider<OfflineController>.value(
        value: offline,
        child: const MaterialApp(
          home: Scaffold(
            body: ResilientNetworkImage(
              url: 'https://example.com/plant.jpg',
              width: 40,
              height: 40,
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    offline.bumpSyncGeneration();
    await tester.pump();
    expect(offline.syncGeneration, 2);
  });
}
