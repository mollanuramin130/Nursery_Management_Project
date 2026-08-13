/// QA-22 push token registration helpers.
/// LIVE FCM requires Firebase project + google-services.json + FCM_SERVER_KEY.
/// Without Firebase, devices still register for inbox; optional dart-define
/// `FCM_PUSH_TOKEN` can supply a token for API integration tests.
library;

String? resolvePushTokenFromEnvironment() {
  const token = String.fromEnvironment('FCM_PUSH_TOKEN', defaultValue: '');
  if (token.isEmpty) return null;
  return token;
}

Map<String, dynamic> deviceRegisterBody({
  required String platform,
  required String deviceId,
  required String appVersion,
  String? pushToken,
}) {
  return {
    'platform': platform,
    'device_id': deviceId,
    'app_version': appVersion,
    if (pushToken != null && pushToken.isNotEmpty) 'push_token': pushToken,
  };
}
