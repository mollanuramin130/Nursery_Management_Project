import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_admin_mobile/theme/app_theme.dart';

void main() {
  test('Only completed or active statuses use success color', () {
    for (final status in ['ACTIVE', 'DELIVERED', 'RECEIVED', 'CONFIRMED', 'IN_STOCK']) {
      expect(statusColor(status), OpsColors.success);
    }
    for (final status in ['INACTIVE', 'OUT_FOR_DELIVERY', 'UNCONFIRMED']) {
      expect(statusColor(status), isNot(OpsColors.success));
    }
    expect(statusColor('CANCELLED'), OpsColors.danger);
    expect(statusColor('PENDING'), OpsColors.warning);
    expect(statusColor(' active '), OpsColors.success);
  });
}
