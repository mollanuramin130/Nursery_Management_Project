import 'package:nursery_app/core/api_client.dart';

/// User-safe copy for auth / account API failures.
abstract final class AuthMessages {
  static String fromException(Object error) {
    if (error is ApiException) {
      return sanitize(error.message, statusCode: error.statusCode);
    }
    return sanitize(error.toString());
  }

  static String sanitize(String? raw, {int? statusCode}) {
    final text = (raw ?? '').trim();
    final lower = text.toLowerCase();

    if (text.isEmpty ||
        lower.contains('dioexception') ||
        lower.contains('socketexception') ||
        lower.contains('connection error') ||
        lower.contains('failed host lookup') ||
        lower.contains('network is unreachable')) {
      return 'Unable to connect. Please check your internet connection.';
    }

    if (lower.contains('timed out') || lower.contains('timeout')) {
      return 'The request timed out. Please try again.';
    }

    if (statusCode == 401 ||
        lower.contains('invalid email or password') ||
        lower.contains('invalid credentials') ||
        lower.contains('unauthenticated')) {
      return 'Email or password is incorrect.';
    }

    if (statusCode == 409 || lower.contains('already registered')) {
      if (text.isNotEmpty && text.length < 160) return text;
      return 'An account with these details already exists.';
    }

    if (statusCode == 422) {
      if (text.isNotEmpty &&
          !lower.contains('exception') &&
          text.length < 160) {
        return text;
      }
      return 'Please check your details and try again.';
    }

    if (statusCode != null && statusCode >= 500) {
      return 'Something went wrong. Please try again.';
    }

    if (lower.contains('exception') ||
        lower.contains('stack') ||
        lower.contains('sqlstate') ||
        RegExp(r'\bhttp\s*\d{3}\b').hasMatch(lower)) {
      return 'Something went wrong. Please try again.';
    }

    return text.length > 160 ? 'Something went wrong. Please try again.' : text;
  }
}
