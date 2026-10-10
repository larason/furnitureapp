import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/attachments/pending_attachment.dart';
import 'package:sl_furnitures/core/auth/auth_session.dart';
import 'package:sl_furnitures/core/network/api_error.dart';
import 'package:sl_furnitures/core/network/api_transport_exception.dart';
import 'package:sl_furnitures/core/network/request_cancellation.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_draft.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_repository.dart';
import 'package:sl_furnitures/features/enquiries/presentation/enquiry_controller.dart';

const _validDraft = EnquiryDraft(
  subject: 'Do you deliver to Dodoma?',
  message: 'I would like to know whether you deliver to Dodoma.',
  name: 'Asha Mushi',
  phone: '+255700000000',
);

void main() {
  test('starts in the editing state with no errors', () {
    final controller = EnquiryController(
      repository: _RecordingRepository(),
      authSession: _StubAuthSession(),
    );

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.errors, isEmpty);
    expect(controller.message, isNull);
    expect(controller.submitted, isNull);

    controller.dispose();
  });

  test('an invalid submit stays local and reports field errors', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(),
    );

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.errors.keys, containsAll(<String>['subject', 'message']));
    expect(repository.callCount, 0);

    controller.dispose();
  });

  test('a valid submit confirms the created enquiry', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.success);
    expect(controller.submitted!.status, 'OPEN');
    expect(repository.callCount, 1);
    expect(repository.lastAuthenticated, isFalse);

    controller.dispose();
  });

  test('a rapid second tap does not dispatch a duplicate request', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(),
    );
    _fill(controller);
    final gate = repository.gate;

    final first = controller.submit();
    final second = controller.submit();
    await gate.future;
    await first;
    await second;

    expect(repository.callCount, 1);
    expect(controller.state, EnquirySubmissionState.success);

    controller.dispose();
  });

  test('a signed-in session that cannot produce a token does not fall back to '
      'an anonymous enquiry', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(signedIn: true, token: null),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.message, contains('signed-in session'));
    expect(repository.callCount, 0);

    controller.dispose();
  });

  test('a signed-in submission carries the authenticated auth mode', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(signedIn: true, token: 'clerk-token'),
    );
    _fill(controller);

    await controller.submit();

    expect(repository.callCount, 1);
    expect(repository.lastAuthenticated, isTrue);

    controller.dispose();
  });

  test('server field errors are mapped onto their controls', () async {
    final controller = EnquiryController(
      repository: _RecordingRepository(
        failure: ApiError(
          statusCode: 422,
          errors: const <ApiErrorItem>[
            ApiErrorItem(
              code: 'INVALID_VALUE',
              message:
                  'The subject field must be between 5 and 200 characters.',
              field: 'subject',
            ),
          ],
          requestId: 'req-422',
        ),
      ),
      authSession: _StubAuthSession(),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.errors['subject'], contains('between 5 and 200'));
    expect(
      controller.message,
      'Correct the highlighted details and try again.',
    );

    controller.dispose();
  });

  test('rate limiting is reported without an invented deadline', () async {
    final controller = EnquiryController(
      repository: _RecordingRepository(
        failure: ApiError(
          statusCode: 429,
          errors: const <ApiErrorItem>[
            ApiErrorItem(code: 'RATE_LIMITED', message: 'Too many requests.'),
          ],
          requestId: 'req-429',
          retryAfterSeconds: 45,
        ),
      ),
      authSession: _StubAuthSession(),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.message, 'Please wait 45 seconds before trying again.');

    controller.dispose();
  });

  test(
    'a connection failure is an uncertain outcome, not a rejection',
    () async {
      final controller = EnquiryController(
        repository: _RecordingRepository(
          failure: const ApiTransportException(
            kind: ApiTransportFailureKind.connection,
          ),
        ),
        authSession: _StubAuthSession(),
      );
      _fill(controller);

      await controller.submit();

      expect(controller.state, EnquirySubmissionState.uncertain);
      expect(controller.isUncertain, isTrue);
      expect(controller.message, contains('may create a duplicate'));
      expect(controller.message, isNot(contains('was not submitted')));

      controller.dispose();
    },
  );

  test('a timeout is an uncertain outcome', () async {
    final controller = EnquiryController(
      repository: _RecordingRepository(
        failure: const ApiTransportException(
          kind: ApiTransportFailureKind.timeout,
        ),
      ),
      authSession: _StubAuthSession(),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.uncertain);

    controller.dispose();
  });

  test('a cancellation is never reported as a server rejection', () async {
    final controller = EnquiryController(
      repository: _RecordingRepository(
        failure: const ApiTransportException(
          kind: ApiTransportFailureKind.cancellation,
        ),
      ),
      authSession: _StubAuthSession(),
    );
    _fill(controller);

    await controller.submit();

    expect(controller.state, EnquirySubmissionState.editing);
    expect(controller.message, isNull);
    expect(controller.isUncertain, isFalse);

    controller.dispose();
  });

  test(
    'the uncertain warning survives editing until the customer retries',
    () async {
      final repository = _RecordingRepository(
        failure: const ApiTransportException(
          kind: ApiTransportFailureKind.connection,
        ),
      );
      final controller = EnquiryController(
        repository: repository,
        authSession: _StubAuthSession(),
      );
      _fill(controller);
      await controller.submit();

      controller.update(
        _validDraft.copyWith(message: 'An edited message body.'),
      );

      expect(controller.state, EnquirySubmissionState.uncertain);
      expect(controller.message, contains('may create a duplicate'));

      await controller.submit();

      expect(repository.callCount, 2);
      expect(controller.state, EnquirySubmissionState.success);

      controller.dispose();
    },
  );

  test('an oversized attachment blocks the submission', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(),
    );
    controller.update(
      _validDraft.copyWith(
        attachment: PendingAttachment(
          name: 'large.pdf',
          bytes: Uint8List(maxAttachmentBytes + 1),
          contentType: 'application/pdf',
        ),
      ),
    );

    await controller.submit();

    expect(controller.errors['attachment'], isNotNull);
    expect(repository.callCount, 0);

    controller.dispose();
  });

  test('disposal cancels an in-flight submission', () async {
    final repository = _RecordingRepository();
    final controller = EnquiryController(
      repository: repository,
      authSession: _StubAuthSession(),
    );
    _fill(controller);
    final gate = repository.gate;
    final pending = controller.submit();

    controller.cancel();
    controller.dispose();
    await gate.future;
    await pending;

    expect(repository.lastCancellation?.isCancelled, isTrue);
  });

  test('an accepted product context is carried into the draft', () {
    final controller = EnquiryController(
      repository: _RecordingRepository(),
      authSession: _StubAuthSession(),
      product: const EnquiryProductContext(id: 'prod_01', name: 'Nordic Sofa'),
    );

    expect(controller.draft.product?.id, 'prod_01');
    expect(controller.draft.product?.name, 'Nordic Sofa');

    controller.dispose();
  });
}

void _fill(EnquiryController controller) => controller.update(_validDraft);

class _StubAuthSession extends ChangeNotifier implements AuthSession {
  _StubAuthSession({this.signedIn = false, this.token});

  bool signedIn;
  String? token;

  @override
  ClerkAuthStatus get status =>
      signedIn ? ClerkAuthStatus.signedIn : ClerkAuthStatus.signedOut;

  @override
  bool get isSignedIn => signedIn;

  @override
  Future<String?> getToken() async => token;

  @override
  Future<void> signOut() async {
    signedIn = false;
    notifyListeners();
  }
}

class _RecordingRepository implements EnquiryRepository {
  _RecordingRepository({this.failure});

  final Object? failure;
  final Completer<void> gate = Completer<void>();
  bool _hasFailed = false;
  int callCount = 0;
  bool? lastAuthenticated;
  RequestCancellation? lastCancellation;

  @override
  Future<SubmittedEnquiry> submit(
    EnquiryDraft draft, {
    required bool authenticated,
    RequestCancellation? cancellation,
  }) async {
    callCount++;
    lastAuthenticated = authenticated;
    lastCancellation = cancellation;
    if (!gate.isCompleted) gate.complete();
    if (failure != null && !_hasFailed) {
      _hasFailed = true;
      throw failure!;
    }
    return const SubmittedEnquiry(
      id: 'enq_01h8y5a1b2c3d4e5f6g7h8j9',
      status: 'OPEN',
    );
  }
}
