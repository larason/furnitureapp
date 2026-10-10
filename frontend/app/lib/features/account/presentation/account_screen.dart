import 'package:clerk_flutter/clerk_flutter.dart';
import 'package:flutter/material.dart';

import '../../../core/presentation/error_presentation_mapper.dart';
import '../../../theme/app_spacing.dart';
import '../data/customer_profile.dart';
import '../data/profile_repository.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    super.key,
    required this.repository,
    this.accountActions,
  });

  final ProfileRepository repository;

  /// Clerk-managed account and security controls.
  ///
  /// Defaults to Clerk's own button. It is a parameter so the screen can be
  /// exercised without an authenticated Clerk provider.
  final Widget? accountActions;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  late Future<CustomerProfile> _profile = widget.repository.getProfile();

  void _reload() => setState(() => _profile = widget.repository.getProfile());

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Account'),
      // Kept outside the profile future so a failed `GET /me` never removes the
      // Clerk-managed sign-out and security controls.
      actions: <Widget>[
        widget.accountActions ?? const ClerkUserButton(showName: false),
        const SizedBox(width: AppSpacing.space4),
      ],
    ),
    body: FutureBuilder<CustomerProfile>(
      future: _profile,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: FilledButton(
              onPressed: _reload,
              child: const Text('Try again'),
            ),
          );
        }
        final profile = snapshot.requireData;
        return ListView(
          padding: const EdgeInsets.all(AppSpacing.space4),
          children: <Widget>[
            Text(
              profile.name ?? profile.email,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: AppSpacing.space6),
            Text('Email', style: Theme.of(context).textTheme.labelLarge),
            Text(profile.email),
            const SizedBox(height: AppSpacing.space4),
            Text('Phone', style: Theme.of(context).textTheme.labelLarge),
            Text(profile.phone ?? 'Not provided'),
            const SizedBox(height: AppSpacing.space6),
            _ProfileForm(
              profile: profile,
              repository: widget.repository,
              onSaved: _reload,
            ),
          ],
        );
      },
    ),
  );
}

class _ProfileForm extends StatefulWidget {
  const _ProfileForm({
    required this.profile,
    required this.repository,
    required this.onSaved,
  });

  final CustomerProfile profile;
  final ProfileRepository repository;
  final VoidCallback onSaved;

  @override
  State<_ProfileForm> createState() => _ProfileFormState();
}

class _ProfileFormState extends State<_ProfileForm> {
  late final TextEditingController _name = TextEditingController(
    text: widget.profile.name,
  );
  late final TextEditingController _phone = TextEditingController(
    text: widget.profile.phone,
  );
  bool _saving = false;
  String? _message;
  Map<String, String> _fieldErrors = const <String, String>{};

  @override
  void didUpdateWidget(covariant _ProfileForm oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!_saving && oldWidget.profile != widget.profile) {
      if (_name.text != (widget.profile.name ?? '')) {
        _name.text = widget.profile.name ?? '';
      }
      if (_phone.text != (widget.profile.phone ?? '')) {
        _phone.text = widget.profile.phone ?? '';
      }
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  /// Saves the profile, keeping the entered values when the save fails.
  ///
  /// Laravel is authoritative, so nothing is reported as saved until it
  /// confirms. A rejected field is mapped onto its control and every other
  /// failure becomes a single safe message; the typed name and phone stay put
  /// so the customer does not lose their work.
  Future<void> _save() async {
    setState(() {
      _saving = true;
      _message = null;
      _fieldErrors = const <String, String>{};
    });
    try {
      final shouldClearPhone =
          _phone.text.trim().isEmpty && widget.profile.phone != null;
      await widget.repository.updateProfile(
        name: _name.text,
        phone: _phone.text,
        clearPhone: shouldClearPhone,
      );
      if (mounted) widget.onSaved();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        final presentation = ErrorPresentationMapper.from(error);
        _message = presentation?.message;
        _fieldErrors =
            presentation?.fieldErrors.map(
              (field, messages) => MapEntry(field, messages.join(' ')),
            ) ??
            const <String, String>{};
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  String? _fieldError(String field) => _fieldErrors[field];

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      TextField(
        controller: _name,
        decoration: InputDecoration(
          labelText: 'Name',
          errorText: _fieldError('name'),
        ),
      ),
      const SizedBox(height: AppSpacing.space3),
      TextField(
        controller: _phone,
        decoration: InputDecoration(
          labelText: 'Phone',
          errorText: _fieldError('phone'),
        ),
        keyboardType: TextInputType.phone,
      ),
      if (_message != null) ...<Widget>[
        const SizedBox(height: AppSpacing.space3),
        Semantics(
          liveRegion: true,
          child: Text(
            _message!,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Theme.of(context).colorScheme.error,
            ),
          ),
        ),
      ],
      const SizedBox(height: AppSpacing.space4),
      FilledButton(
        onPressed: _saving ? null : _save,
        child: const Text('Save profile'),
      ),
    ],
  );
}
