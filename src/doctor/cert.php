<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHO Accepted Applications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Global CSS -->
    <link rel="stylesheet" href="../../assets/css/global/base.css">
    <link rel="stylesheet" href="../../assets/css/global/layout.css">
    <link rel="stylesheet" href="../../assets/css/global/component.css">

    <style>
/* ================= CERTIFICATE STYLES ================= */
.certificate-wrapper {
  background: #f2f2f2;
  padding: 20px;
}

.certificate-page {
  width: 210mm;
  min-height: 297mm;
  margin: auto;
  background: #ffffff;
  padding: 35mm 30mm;
  box-shadow: 0 0 10px rgba(0,0,0,.15);
  font-family: "Times New Roman", serif;
}

.cert-header {
  text-align: center;
  position: relative;
}
.cert-left {
  position: absolute;
  left: -40px;
  top: -15px;
  width: 120px;
}
.cert-right {
  position: absolute;
  right: -20px;
  top: 0;
  width: 90px;
}

.cert-office {
  font-size: 26px;
  font-weight: bold;
  color: #8b1d2c;
}

.cert-header p,
.cert-header .cert-office {
  line-height: 1.5; 
  margin: 0 0 0.2rem; 
}

.cert-title {
  margin: 30px 0;
  font-size: 34px;
  font-weight: bold;
  color: #1f6b1f;
}

.cert-content {
  font-size: 18px;
  line-height: 1.6;
  text-align: justify;
}

.cert-disability {
  text-align: center;
  font-size: 28px;
  font-weight: bold;
  margin: 30px 0;
}

.cert-footer {
  display: flex;
  justify-content: space-between;
  margin-top: 60px;
}

.cert-footer img {
  width: 140px;
}

.cert-signature {
  text-align: right;
  font-size: 18px;
}

@media print {
  body * { visibility: hidden; }
  .certificate-page, .certificate-page * {
    visibility: visible;
  }
  .certificate-page {
    position: absolute;
    left: 0;
    top: 0;
    box-shadow: none;
  }
}
</style>   
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../../assets/pictures/white.png" alt="logo" width="45">
            <img src="../../assets/pictures/CHO logo.png" alt="logo 2" width="45">
            <h4>CHO</h4>
        </div>
        <hr> 
        <a class="active"><i class="fas fa-chart-line me-2"></i><span>Dashboard</span></a>
        <a><i class="fas fa-wheelchair me-2"></i><span>Members</span></a>
        <a><i class="fas fa-users me-2"></i><span>Applications</span></a>

        <div class="sidebar-item">
            <div class="toggle-btn d-flex justify-content-between align-items-center">
                <span class="no-wrap d-flex align-items-center"><i class="fas fa-folder me-2"></i><span>Manage
                        Applications</span></span>
                <i class="fas fa-chevron-down chevron-icon"></i>
            </div>
            <div class="submenu">
                <a href="#" class="submenu-link d-flex align-items-center ps-4"
                    style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-user-check"></i></span>
                    <span class="ms-2">Accepted</span>
                </a>
                <a href="#" class="submenu-link d-flex align-items-center ps-4"
                    style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-hourglass-half"></i></span>
                    <span class="ms-2">Pending</span>
                </a>
                <a href="#" class="submenu-link d-flex align-items-center ps-4"
                    style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-user-times"></i></span>
                    <span class="ms-2">Denied</span>
                </a>
            </div>
        </div>

        <a><i class="fas fa-sign-out-alt me-2"></i><span>Logout</span></a>
    </div>

    <div class="main">
        <div class="topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
            </div>

            <div class="d-flex flex-column align-items-end">
                <div class="d-flex align-items-center ms-3 mt-2 mb-2" style="font-size: 1.4rem;">
                    <strong>Danny Boy Loberanes Jr.</strong>
                    <i class="fas fa-user-circle ms-3 me-2 mb-2 mt-2" style="font-size: 2.5rem;"></i>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-sm btn-primary" onclick="downloadCertificate()" title="Download Certificate">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                    <button class="btn btn-sm btn-success" onclick="printCertificate()" title="Print Certificate">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>

        <!-- CERTIFICATE CONTENT -->
  <div class="certificate-wrapper mt-4">

    <div class="certificate-page">

      <div class="cert-header">
        <img src="../../assets/pictures/LGU.png" class="cert-left">
        <img src="../../assets/pictures/CHO logo.png" class="cert-right">

        <p>Republic of the Philippines</p>
        <p>City of Iligan</p>
        <div class="cert-office">Office of the City Health Officer</div>
        <p>Gen. Aguinaldo Street, Iligan City, 9200</p>
      </div>

      <div class="cert-title text-center">
        CERTIFICATION OF DISABILITY
      </div>

      <div class="cert-content">
        <p>To Whom It May Concern</p>

        <p>
          This is to certify that <strong>Paolo Miguel Larrazabal Baquiano</strong>,
          <strong>29</strong> / <strong>Male</strong>, <strong>Single</strong>,
          a resident of <strong>Barangay San Miguel</strong>,
          has voluntarily submitted herself/himself to this office with regard
          to the nature of disability.
        </p>

        <p>
          Based on the personal interview and assessment conducted by herein physician,
          the patient has a diagnosis of <strong>ADHD</strong> that resulted to:
        </p>

        <div class="cert-disability">
          Psychosocial Disability
        </div>

        <p>
          As classified by the National council on Disability Affairs Administrative Order 2021-001. 
          This certification is issued on <strong>January 14, 2025</strong> in the Iligan City Health Office
          in compliance with the requirement in issuance of PWD-IDC for the benefits and privileges of persons 
          with disabilities as mandated by <strong>Republic Act No. 9442 or Magna Carta for Persons with Disabilities</strong>,
          and not for medico legal purposes.
        </p>
      </div>

      <div class="cert-footer">
        <img src="../../assets/pictures/qr-sample.png">

        <div class="cert-signature">
          <strong>Taisha Rose Magadan Lisondra, MD</strong><br>
          CHO Medical Officer<br>
          Lic. No. 0159859
        </div>
      </div>

    </div>
  </div>
</div>


        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const submenu = btn.nextElementSibling;
                const icon = btn.querySelector('.chevron-icon');
                submenu.style.maxHeight = submenu.style.maxHeight ? null : submenu.scrollHeight + "px";
                icon.classList.toggle('rotate');
            });
        });

        // Toggle Sidebar visibility
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const main = document.querySelector('.main');
            sidebar.classList.toggle('closed');
            main.classList.toggle('shifted');
        }

        // Download Certificate as PDF
        function downloadCertificate() {
            const element = document.querySelector('.certificate-page');
            const opt = {
                margin: 0,
                filename: 'Certificate_of_Disability.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }

        // Print Certificate
        function printCertificate() {
            window.print();
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</body>

</html>
