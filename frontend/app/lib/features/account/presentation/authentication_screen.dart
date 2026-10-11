import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

import '../../../core/presentation/app_brand_logo.dart';
import '../../../theme/app_spacing.dart';

class AuthenticationScreen extends StatelessWidget {
  const AuthenticationScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Column(
        children: <Widget>[
          const Padding(
            padding: EdgeInsets.only(top: AppSpacing.space5),
            child: AppBrandLogo(height: AppSpacing.space7),
          ),
          const SizedBox(height: AppSpacing.space2),
          const Expanded(child: ClerkAuthentication()),
        ],
      ),
    ),
  );
}
