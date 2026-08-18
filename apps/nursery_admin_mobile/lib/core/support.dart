import 'dart:math';

import 'package:url_launcher/url_launcher.dart';

const kGreenLeafSupportPhone = '8926627220';

Uri supportTelUri() => Uri(scheme: 'tel', path: kGreenLeafSupportPhone);

String newErrorReference([int Function()? nextNibble]) {
  const hex = '0123456789ABCDEF';
  final buf = StringBuffer('GL-');
  final next = nextNibble ?? () => Random().nextInt(16);
  for (var i = 0; i < 5; i++) {
    buf.write(hex[next() & 0x0F]);
  }
  return buf.toString();
}

bool looksLikeErrorReference(String value) =>
    RegExp(r'^GL-[0-9A-F]{5}$').hasMatch(value);

Future<bool> openSupportDialer() async {
  final uri = supportTelUri();
  try {
    if (await canLaunchUrl(uri)) {
      return launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  } catch (_) {
    return false;
  }
  return false;
}
