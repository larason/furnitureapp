import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:sl_furnitures/core/attachments/pending_attachment.dart';
import 'package:sl_furnitures/features/enquiries/data/enquiry_draft.dart';

void main() {
  group('subject bounds', () {
    test('rejects a missing subject', () {
      final errors = validateEnquiry(
        const EnquiryDraft(message: 'A message long enough to submit.'),
        authenticated: false,
      );

      expect(errors['subject'], isNotNull);
    });

    test('rejects a subject below the minimum length', () {
      final errors = validateEnquiry(
        const EnquiryDraft(subject: 'Hi', message: 'A message long enough.'),
        authenticated: false,
      );

      expect(errors['subject'], isNotNull);
    });

    test('accepts a subject at the minimum length', () {
      final errors = validateEnquiry(
        const EnquiryDraft(
          subject: 'About',
          message: 'A message long enough.',
          name: 'Asha Mushi',
          phone: '+255700000000',
        ),
        authenticated: false,
      );

      expect(errors, isEmpty);
    });

    test('rejects a subject above the maximum length', () {
      final errors = validateEnquiry(
        EnquiryDraft(
          subject: 'a' * (enquirySubjectMaxLength + 1),
          message: 'A message long enough.',
          name: 'Asha Mushi',
          phone: '+255700000000',
        ),
        authenticated: false,
      );

      expect(errors['subject'], isNotNull);
    });
  });

  group('message bounds', () {
    test('rejects a missing message', () {
      final errors = validateEnquiry(
        const EnquiryDraft(
          subject: 'A subject',
          name: 'Asha Mushi',
          phone: '+255700000000',
        ),
        authenticated: false,
      );

      expect(errors['message'], isNotNull);
    });

    test('rejects a message below the minimum length', () {
      final errors = validateEnquiry(
        const EnquiryDraft(
          subject: 'A subject',
          message: 'short',
          name: 'Asha Mushi',
          phone: '+255700000000',
        ),
        authenticated: false,
      );

      expect(errors['message'], isNotNull);
    });

    test('rejects a message above the maximum length', () {
      final errors = validateEnquiry(
        EnquiryDraft(
          subject: 'A subject',
          message: 'a' * (enquiryMessageMaxLength + 1),
          name: 'Asha Mushi',
          phone: '+255700000000',
        ),
        authenticated: false,
      );

      expect(errors['message'], isNotNull);
    });
  });

  group('anonymous contact rules', () {
    const enquiry = EnquiryDraft(
      subject: 'A subject',
      message: 'A message long enough.',
    );

    test('requires a name', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(phone: '+255700000000'),
          authenticated: false,
        )['name'],
        isNotNull,
      );
    });

    test('requires at least one of phone or email', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(name: 'Asha Mushi'),
          authenticated: false,
        )['contact'],
        isNotNull,
      );
    });

    test('accepts a name with only an email', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(name: 'Asha Mushi', email: 'asha@example.com'),
          authenticated: false,
        ),
        isEmpty,
      );
    });
  });

  group('authenticated contact rules', () {
    test('does not require contact the account can derive', () {
      final errors = validateEnquiry(
        const EnquiryDraft(
          subject: 'A subject',
          message: 'A message long enough.',
        ),
        authenticated: true,
      );

      expect(errors, isEmpty);
    });
  });

  group('contact formats', () {
    const enquiry = EnquiryDraft(
      subject: 'A subject',
      message: 'A message long enough.',
      name: 'Asha Mushi',
    );

    test('accepts a valid email address', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(email: 'asha@example.com'),
          authenticated: false,
        ),
        isEmpty,
      );
    });

    test('rejects an invalid email address', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(email: 'asha.example.com'),
          authenticated: false,
        )['email'],
        isNotNull,
      );
    });

    test('rejects an email above the maximum length', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(email: '${'a' * 250}@example.com'),
          authenticated: false,
        )['email'],
        isNotNull,
      );
    });

    test('accepts a valid phone number', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(phone: '+255 700 000 000'),
          authenticated: false,
        ),
        isEmpty,
      );
    });

    test('rejects an invalid phone number', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(phone: 'call-me'),
          authenticated: false,
        )['phone'],
        isNotNull,
      );
    });

    test('rejects a name above the maximum length', () {
      expect(
        validateEnquiry(
          enquiry.copyWith(
            name: 'a' * (enquiryNameMaxLength + 1),
            phone: '+255700000000',
          ),
          authenticated: false,
        )['name'],
        isNotNull,
      );
    });
  });

  group('product association', () {
    test('accepts an optional catalog product reference', () {
      final errors = validateEnquiry(
        const EnquiryDraft(
          subject: 'A subject',
          message: 'A message long enough.',
          name: 'Asha Mushi',
          phone: '+255700000000',
          product: EnquiryProductContext(id: 'prod_01', name: 'Nordic Sofa'),
        ),
        authenticated: false,
      );

      expect(errors, isEmpty);
    });

    test('a general enquiry needs no product', () {
      expect(
        validateEnquiry(
          const EnquiryDraft(
            subject: 'A subject',
            message: 'A message long enough.',
            name: 'Asha Mushi',
            phone: '+255700000000',
          ),
          authenticated: false,
        ),
        isEmpty,
      );
    });
  });

  group('attachment', () {
    const enquiry = EnquiryDraft(
      subject: 'A subject',
      message: 'A message long enough.',
      name: 'Asha Mushi',
      phone: '+255700000000',
    );

    test('accepts each supported content type', () {
      for (final contentType in attachmentContentTypes) {
        expect(
          validateEnquiry(
            enquiry.copyWith(
              attachment: PendingAttachment(
                name: 'reference',
                bytes: Uint8List.fromList(<int>[1, 2, 3]),
                contentType: contentType,
              ),
            ),
            authenticated: false,
          ),
          isEmpty,
          reason: '$contentType must be accepted',
        );
      }
    });

    test('rejects an unsupported content type', () {
      final errors = validateEnquiry(
        enquiry.copyWith(
          attachment: PendingAttachment(
            name: 'notes.txt',
            bytes: Uint8List.fromList(<int>[1]),
            contentType: 'text/plain',
          ),
        ),
        authenticated: false,
      );

      expect(errors['attachment'], isNotNull);
    });

    test('rejects a file above the shared byte limit', () {
      final errors = validateEnquiry(
        enquiry.copyWith(
          attachment: PendingAttachment(
            name: 'large.pdf',
            bytes: Uint8List(maxAttachmentBytes + 1),
            contentType: 'application/pdf',
          ),
        ),
        authenticated: false,
      );

      expect(errors['attachment'], isNotNull);
    });

    test('maps a picked extension to a supported content type', () {
      expect(attachmentContentTypeForExtension('JPG'), 'image/jpeg');
      expect(attachmentContentTypeForExtension('pdf'), 'application/pdf');
      expect(attachmentContentTypeForExtension('txt'), isNull);
      expect(attachmentContentTypeForExtension(null), isNull);
    });
  });

  test('an uncertain draft keeps only one attachment', () {
    final draft = const EnquiryDraft().copyWith(
      attachment: PendingAttachment(
        name: 'a.png',
        bytes: Uint8List.fromList(<int>[1]),
        contentType: 'image/png',
      ),
    );

    expect(draft.copyWith(clearAttachment: true).attachment, isNull);
    expect(draft.copyWith().attachment, same(draft.attachment));
  });

  group('response decoding', () {
    test('reads the documented identity and status only', () {
      final submitted = SubmittedEnquiry.fromJson(<String, Object?>{
        'id': 'enq_01h8y5a1b2c3d4e5f6g7h8j9',
        'enquiry_status': 'OPEN',
        'name': 'Asha Mushi',
        'staff_internal_notes': 'internal',
      });

      expect(submitted.id, 'enq_01h8y5a1b2c3d4e5f6g7h8j9');
      expect(submitted.status, 'OPEN');
    });

    test('rejects a payload missing the status', () {
      expect(
        () => SubmittedEnquiry.fromJson(<String, Object?>{
          'id': 'enq_01h8y5a1b2c3d4e5f6g7h8j9',
        }),
        throwsFormatException,
      );
    });
  });
}
