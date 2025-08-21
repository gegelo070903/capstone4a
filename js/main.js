function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Full Reports", 10, 10);
    doc.save("full_reports.pdf");
    // function ppara mag export sang pdf file (not yet fix)
}