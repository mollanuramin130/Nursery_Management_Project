/// Matches API RegisterRequest / ResetPasswordRequest Password rules.
abstract final class PasswordRules {
  static const hint =
      'Use 8+ characters with upper & lower case letters and a number.';

  static bool hasMinLen(String password) => password.length >= 8;

  static bool hasLetter(String password) =>
      RegExp(r'[A-Za-z]').hasMatch(password);

  static bool hasMixedCase(String password) =>
      RegExp(r'[a-z]').hasMatch(password) &&
      RegExp(r'[A-Z]').hasMatch(password);

  static bool hasNumber(String password) => RegExp(r'\d').hasMatch(password);

  static bool isValid(String password) =>
      hasMinLen(password) &&
      hasLetter(password) &&
      hasMixedCase(password) &&
      hasNumber(password);

  static String? validate(String? value) {
    if (value == null || value.isEmpty) {
      return 'Enter a password.';
    }
    if (!isValid(value)) {
      return hint;
    }
    return null;
  }
}
