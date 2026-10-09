import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../theme/app_spacing.dart';

class AppBrandLogo extends StatelessWidget {
  const AppBrandLogo({super.key});

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'SL Furnitures',
    image: true,
    child: SvgPicture.asset(
      'assets/applogo/brandlogo.svg',
      excludeFromSemantics: true,
      height: AppSpacing.space8,
      fit: BoxFit.contain,
    ),
  );
}
