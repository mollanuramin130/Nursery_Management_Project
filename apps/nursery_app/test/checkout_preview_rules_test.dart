import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/checkout_preview_rules.dart';

void main() {
  group('CheckoutPreviewRules (QA-CHK-001)', () {
    test('never falls back to cart total', () {
      expect(CheckoutPreviewRules.payableTotal(null), isNull);
      expect(CheckoutPreviewRules.payableTotal({}), isNull);
      expect(
        CheckoutPreviewRules.payableTotal({'grand_total': 722.0}),
        722.0,
      );
    });

    test('blocks place without preview', () {
      expect(
        CheckoutPreviewRules.canPlace(
          preview: null,
          busy: false,
          submissionLocked: false,
        ),
        isFalse,
      );
      expect(
        CheckoutPreviewRules.canPlace(
          preview: {'grand_total': 100},
          busy: true,
          submissionLocked: false,
        ),
        isFalse,
      );
      expect(
        CheckoutPreviewRules.canPlace(
          preview: {'grand_total': 100},
          busy: false,
          submissionLocked: true,
        ),
        isFalse,
      );
      expect(
        CheckoutPreviewRules.canPlace(
          preview: {'grand_total': 100},
          busy: false,
          submissionLocked: false,
        ),
        isTrue,
      );
    });
  });

  group('CheckoutPreviewRules (QA-PERF-011)', () {
    test('stale generation is rejected', () {
      expect(CheckoutPreviewRules.nextPreviewGeneration(0), 1);
      expect(CheckoutPreviewRules.shouldApplyPreviewResult(3, 3), isTrue);
      expect(CheckoutPreviewRules.shouldApplyPreviewResult(2, 3), isFalse);
      expect(CheckoutPreviewRules.shouldApplyPreviewResult(1, 3), isFalse);
    });
  });
}
