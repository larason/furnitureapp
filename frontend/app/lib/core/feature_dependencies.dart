import 'auth/auth_session.dart';
import 'network/api_client.dart';

/// Shared services supplied to a feature at its composition boundary.
///
/// This object carries references only. The application composition root owns
/// creation and disposal of the services it passes here.
class FeatureDependencies {
  const FeatureDependencies({required this.apiClient, this.authSession});

  final ApiClient apiClient;
  final AuthSession? authSession;
}
