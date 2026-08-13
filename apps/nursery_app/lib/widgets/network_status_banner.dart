import 'package:flutter/material.dart';
import 'package:nursery_app/core/network_errors.dart';
import 'package:nursery_app/providers/network_status_provider.dart';
import 'package:nursery_app/providers/offline_controller.dart';
import 'package:nursery_app/theme/tokens.dart';
import 'package:provider/provider.dart';

/// Compact degraded / syncing banner — content stays visible (QA-37/38).
class NetworkStatusBanner extends StatelessWidget {
  const NetworkStatusBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final net = context.watch<NetworkStatusProvider>();
    final offlineCtrl = context.watch<OfflineController>();

    final show =
        net.showBanner || offlineCtrl.servingLocal || offlineCtrl.syncing;
    if (!show) return const SizedBox.shrink();

    final syncing = offlineCtrl.syncing;
    final offline =
        !syncing &&
        (net.kind == NetworkKind.offline || offlineCtrl.servingLocal);
    final bg = syncing
        ? AppColors.primarySoft
        : offline
            ? AppColors.warningSoft
            : AppColors.primarySoft;
    final fg = syncing
        ? AppColors.primaryDeep
        : offline
            ? AppColors.warning
            : AppColors.primaryDeep;

    final text = offlineCtrl.localBannerSuffix(net.kind) ??
        (net.showBanner ? net.bannerText : 'Showing your saved GreenLeaf data');

    return Semantics(
      liveRegion: true,
      label: text,
      child: Material(
        color: bg,
        child: SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpace.md,
              vertical: AppSpace.sm,
            ),
            child: Row(
              children: [
                if (syncing)
                  SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: fg,
                    ),
                  )
                else
                  Icon(
                    offline ? Icons.wifi_off_rounded : Icons.cloud_off_outlined,
                    size: 18,
                    color: fg,
                  ),
                const SizedBox(width: AppSpace.sm),
                Expanded(
                  child: Text(
                    text,
                    style: TextStyle(
                      color: fg,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
                if (!syncing)
                  TextButton(
                    onPressed: () => net.retryNow(),
                    style: TextButton.styleFrom(
                      foregroundColor: fg,
                      visualDensity: VisualDensity.compact,
                    ),
                    child: const Text('Retry'),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
