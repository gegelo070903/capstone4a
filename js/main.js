function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Full Reports", 10, 10);
    // You can loop through the tables and add content as needed
    doc.save("full_reports.pdf");
}