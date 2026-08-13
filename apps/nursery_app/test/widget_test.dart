import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nursery_app/core/auth_messages.dart';
import 'package:nursery_app/core/auth_navigation.dart';
import 'package:nursery_app/models/models.dart';
import 'package:nursery_app/theme/app_theme.dart';
import 'package:nursery_app/widgets/app_button.dart';
import 'package:nursery_app/widgets/app_search_field.dart';
import 'package:nursery_app/widgets/ui_kit.dart';

void main() {
  test('money formats INR with commas', () {
    expect(money(299), '₹299');
    expect(money(1299), '₹1,299');
    expect(money(1299.5), '₹1,299.50');
  });

  test('formatOrderDate formats ISO timestamps', () {
    final formatted = formatOrderDate('2026-08-11T10:30:00Z');
    expect(formatted.contains('2026'), isTrue);
    expect(formatted.contains('Aug'), isTrue);
  });

  test('AuthNavigation sanitizes redirect intent', () {
    expect(AuthNavigation.sanitizeRedirect('/checkout'), '/checkout');
    expect(AuthNavigation.sanitizeRedirect('/login'), isNull);
    expect(AuthNavigation.sanitizeRedirect('https://evil.com'), isNull);
    expect(AuthNavigation.sanitizeRedirect('//evil.com'), isNull);
    expect(
      AuthNavigation.loginLocation(redirect: '/checkout'),
      contains(Uri.encodeComponent('/checkout')),
    );
  });

  test('AuthMessages hides technical errors', () {
    expect(
      AuthMessages.sanitize('DioException [connection error]'),
      contains('internet'),
    );
    expect(
      AuthMessages.sanitize('Invalid email or password', statusCode: 401),
      'Invalid email or password.',
    );
    expect(
      AuthMessages.sanitize('Account is blocked', statusCode: 401),
      'Your account is currently unavailable. Please contact support.',
    );
  });

  test('ErrorStateView sanitizes technical errors', () {
    expect(
      ErrorStateView.sanitize('DioException [connection error]'),
      contains('nursery'),
    );
    expect(ErrorStateView.sanitize('Coupon expired'), 'Coupon expired');
  });

  testWidgets('AppButton shows loading label and disables press', (
    tester,
  ) async {
    var pressed = false;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: AppButton(
            label: 'Add',
            loading: true,
            loadingLabel: 'Adding…',
            onPressed: () => pressed = true,
          ),
        ),
      ),
    );
    expect(find.text('Adding…'), findsOneWidget);
    await tester.tap(find.byType(FilledButton));
    expect(pressed, isFalse);
  });

  testWidgets('AppSearchField shows hint', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(body: AppSearchField(readOnly: true)),
      ),
    );
    expect(find.text('Search plants, pots, seeds…'), findsOneWidget);
  });

  testWidgets('EmptyStateView renders title and CTA', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: EmptyStateView(
            title: 'Your garden is waiting.',
            message: 'Add plants.',
            actionLabel: 'Explore plants',
            onAction: () {},
          ),
        ),
      ),
    );
    expect(find.text('Your garden is waiting.'), findsOneWidget);
    expect(find.text('Explore plants'), findsOneWidget);
  });

  testWidgets('AppBadge renders label', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(body: AppBadge(label: 'Bestseller')),
      ),
    );
    expect(find.text('BESTSELLER'), findsOneWidget);
  });
}
