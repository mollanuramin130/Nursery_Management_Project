import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/shared/widgets.dart';
import 'package:flutter/material.dart';

void main() {
  testWidgets('QA-42 OpsStaleBanner shows retry when data kept', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: OpsStaleBanner(
            message: 'Could not refresh',
            onRetry: () => tapped = true,
          ),
        ),
      ),
    );
    expect(find.text('Could not refresh'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    expect(tapped, isTrue);
  });
}
