<?php
// Safety helper
function v($key, $fallback = '-') {
    return !empty($GLOBALS['draftData'][$key])
        ? htmlspecialchars($GLOBALS['draftData'][$key])
        : $fallback;
}
?>

<div class="bg-white rounded shadow p-6 space-y-8">

  <!-- ================= PERSONAL INFORMATION ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Personal Information
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
      <div>
        <p class="text-gray-500">First Name</p>
        <p class="font-medium"><?= v('first_name') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Last Name</p>
        <p class="font-medium"><?= v('last_name') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Middle Name</p>
        <p class="font-medium"><?= v('middle_name') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Birthdate</p>
        <p class="font-medium"><?= v('birthdate') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Sex</p>
        <p class="font-medium"><?= v('sex') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Civil Status</p>
        <p class="font-medium"><?= v('civil_status') ?></p>
      </div>
    </div>
  </section>

  <!-- ================= ADDRESS ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Address
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
      <div>
        <p class="text-gray-500">House No. / Street</p>
        <p class="font-medium"><?= v('house_no_street') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Barangay</p>
        <p class="font-medium"><?= v('barangay') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Municipality / City</p>
        <p class="font-medium"><?= v('municipality') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Province</p>
        <p class="font-medium"><?= v('province') ?></p>
      </div>
    </div>
  </section>

  <!-- ================= CONTACT DETAILS ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Contact Information
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
      <div>
        <p class="text-gray-500">Mobile Number</p>
        <p class="font-medium"><?= v('mobile_no') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Landline</p>
        <p class="font-medium"><?= v('landline_no') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Email Address</p>
        <p class="font-medium"><?= v('email_address') ?></p>
      </div>
    </div>
  </section>

  <!-- ================= EMPLOYMENT ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Employment Information
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
      <div>
        <p class="text-gray-500">Educational Attainment</p>
        <p class="font-medium"><?= v('educational_attainment') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Occupation</p>
        <p class="font-medium"><?= v('occupation') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Employment Status</p>
        <p class="font-medium"><?= v('employment_status') ?></p>
      </div>
    </div>
  </section>

  <!-- ================= ORGANIZATION ================= -->
<section>
  <h2 class="text-lg font-semibold mb-4 border-b pb-2">
    Organization Affiliation
  </h2>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">

    <div>
      <p class="text-gray-500">Organization</p>
      <p class="font-medium"><?= v('organization_affiliated') ?></p>
    </div>

    <div>
      <p class="text-gray-500">Office Address</p>
      <p class="font-medium"><?= v('office_address') ?></p>
    </div>

  </div>
</section>

  <!-- ================= GOVERNMENT IDS ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Government Identification
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-sm">
      <div>
        <p class="text-gray-500">SSS No.</p>
        <p class="font-medium"><?= v('sss_no') ?></p>
      </div>

      <div>
        <p class="text-gray-500">GSIS No.</p>
        <p class="font-medium"><?= v('gsis_no') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Pag-IBIG No.</p>
        <p class="font-medium"><?= v('pagibig_no') ?></p>
      </div>

      <div>
        <p class="text-gray-500">PhilHealth No.</p>
        <p class="font-medium"><?= v('philhealth_no') ?></p>
      </div>
    </div>
  </section>

  <!-- ================= FAMILY BACKGROUND ================= -->
<section>
  <h2 class="text-lg font-semibold mb-4 border-b pb-2">
    Family Background
  </h2>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">

    <div>
      <p class="text-gray-500">Father's Name</p>
      <p class="font-medium">
        <?= v('father_first_name') ?>
        <?= v('father_middle_name') ?>
        <?= v('father_last_name') ?>
      </p>
    </div>

    <div>
      <p class="text-gray-500">Mother's Name</p>
      <p class="font-medium">
        <?= v('mother_first_name') ?>
        <?= v('mother_middle_name') ?>
        <?= v('mother_last_name') ?>
      </p>
    </div>

  </div>
</section>

  <!-- ================= EMERGENCY CONTACT ================= -->
  <section>
    <h2 class="text-lg font-semibold mb-4 border-b pb-2">
      Emergency Contact
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
      <div>
        <p class="text-gray-500">Contact Person</p>
        <p class="font-medium"><?= v('contact_person_name') ?></p>
      </div>

      <div>
        <p class="text-gray-500">Contact Number</p>
        <p class="font-medium"><?= v('contact_person_no') ?></p>
      </div>
    </div>
  </section>

  ```php
<!-- ================= DOCUMENT REQUIREMENTS ================= -->
<section>
  <h2 class="text-lg font-semibold mb-4 border-b pb-2">
    Uploaded Documents
  </h2>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">

    <div>
      <p class="text-gray-500">1x1 Photo</p>
      <?php if(!empty($draftData['bodypic_path'])): ?>
        <img src="<?= htmlspecialchars($draftData['bodypic_path']) ?>" class="w-32 rounded shadow">
      <?php else: ?>
        <p class="text-gray-400">Not uploaded</p>
      <?php endif; ?>
    </div>

    <div>
      <p class="text-gray-500">Barangay Certificate</p>
      <?php if(!empty($draftData['barangaycert_path'])): ?>
        <a href="<?= htmlspecialchars($draftData['barangaycert_path']) ?>" target="_blank" class="text-blue-600 underline">
          View File
        </a>
      <?php else: ?>
        <p class="text-gray-400">Not uploaded</p>
      <?php endif; ?>
    </div>

    <div>
      <p class="text-gray-500">Medical Certificate</p>
      <?php if(!empty($draftData['medicalcert_path'])): ?>
        <a href="<?= htmlspecialchars($draftData['medicalcert_path']) ?>" target="_blank" class="text-blue-600 underline">
          View File
        </a>
      <?php else: ?>
        <p class="text-gray-400">Not uploaded</p>
      <?php endif; ?>
    </div>

    <div>
      <p class="text-gray-500">Proof of Disability</p>
      <?php if(!empty($draftData['proof_disability_path'])): ?>
        <a href="<?= htmlspecialchars($draftData['proof_disability_path']) ?>" target="_blank" class="text-blue-600 underline">
          View File
        </a>
      <?php else: ?>
        <p class="text-gray-400">Not uploaded</p>
      <?php endif; ?>
    </div>

  </div>
</section>
```


</div>
