import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

import '../../../core/presentation/app_brand_logo.dart';
import '../../../theme/app_spacing.dart';

class AuthenticationScreen extends StatefulWidget {
  const AuthenticationScreen({super.key, this.startInSignUp = false});

  final bool startInSignUp;

  @override
  State<AuthenticationScreen> createState() => _AuthenticationScreenState();
}

class _AuthenticationScreenState extends State<AuthenticationScreen> {
  bool _signUpStarted = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!widget.startInSignUp || _signUpStarted) return;
    _signUpStarted = true;
    WidgetsBinding.instance.addPostFrameCallback((_) => _startSignUp());
  }

  Future<void> _startSignUp() async {
    if (!mounted) return;
    final auth = ClerkAuth.of(context, listen: false);
    if (auth.isNotAvailable || auth.isSigningUp || auth.isSignedIn) return;
    await auth.safelyCall(context, () => auth.attemptSignUp());
  }

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
