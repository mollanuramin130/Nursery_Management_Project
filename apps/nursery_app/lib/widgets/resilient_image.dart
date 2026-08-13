import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:nursery_app/data/mock_asset_store.dart';
import 'package:nursery_app/theme/tokens.dart';

/// Remote image with local asset fallback — never shows a broken-image icon.
class ResilientNetworkImage extends StatelessWidget {
  const ResilientNetworkImage({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
  });

  final String? url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final child = _buildInner();
    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: child);
    }
    return child;
  }

  Widget _buildInner() {
    final u = url?.trim();
    if (u == null || u.isEmpty) {
      return _Fallback(width: width, height: height);
    }
    return CachedNetworkImage(
      imageUrl: u,
      width: width,
      height: height,
      fit: fit,
      placeholder: (_, __) => _Placeholder(width: width, height: height),
      errorWidget: (_, __, ___) => _Fallback(width: width, height: height),
    );
  }
}

class _Placeholder extends StatelessWidget {
  const _Placeholder({this.width, this.height});
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      color: AppColors.surfaceMuted,
      alignment: Alignment.center,
      child: const SizedBox(
        width: 22,
        height: 22,
        child: CircularProgressIndicator(strokeWidth: 2),
      ),
    );
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({this.width, this.height});
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      color: AppColors.primarySoft,
      alignment: Alignment.center,
      child: Image.asset(
        MockAssetStore.placeholderImage,
        width: (width != null && width! < 48) ? width : 40,
        height: (height != null && height! < 48) ? height : 40,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Icon(
          Icons.local_florist_rounded,
          color: AppColors.primary,
          size: 28,
        ),
      ),
    );
  }
}
