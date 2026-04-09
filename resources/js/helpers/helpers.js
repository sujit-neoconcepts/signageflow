import moment from "moment";

export const formatedDate = (date) => {
    return moment(date).format("YYYY-MM-DD HH:mm:ss");
};

export const formatDate = (date) => {
    if (!date) return "";
    return moment(date).format("YYYY-MM-DD");
};

// Get today's date as yyyy-MM-dd string (for VueDatePicker with model-type="yyyy-MM-dd")
export const getTodayString = () => {
    return moment().format("YYYY-MM-DD");
};

export const formatDateTime = (date) => {
    if (!date) return "";
    return moment(date).format("DD-MM-YYYY: HH:mm:ss");
};

export const numberFormat = (number, decimals = 2) => {
    if (number === null || number === undefined) return "";
    return Number(number).toFixed(decimals);
};

export const formatNumber = (number, decimals = 2) => {
    if (number === null || number === undefined) return "";
    return Number(number).toLocaleString("en-US", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
};

export const formatCurrency = (amount, currency = "₹", decimals = 2) => {
    if (amount === null || amount === undefined) return "";
    return (
        currency +
        " " +
        Number(amount).toLocaleString("en-US", {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        })
    );
};

// Print modal table function
export const printModalTable = (tableId, title = "Table Details") => {
    const printContent = document.getElementById(tableId).outerHTML;

    const printWindow = window.open("", "_blank");
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .total-row { font-weight: bold; background-color: #f0f0f0; }
                @media print {
                    body { margin: 0; }
                    table { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
};

// CSV download helper function
export const downloadCSV = (csv, filename) => {
    const csvFile = new Blob([csv], { type: "text/csv" });
    const downloadLink = document.createElement("a");

    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";

    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
};

// Export modal table to CSV
export const exportModalTableToCSV = (tableId, filename, toastFunction) => {
    const csv = [];
    const rows = document.querySelectorAll(`table#${tableId} tr`);

    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll("td, th");

        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText.trim();
            // Clean up text and handle special characters
            cellText = cellText.replace(/\s+/g, " "); // Replace multiple spaces with single space
            cellText = cellText.replace(/"/g, '""'); // Escape quotes
            // Handle commas, quotes, and newlines by wrapping in quotes
            if (
                cellText.indexOf(",") > -1 ||
                cellText.indexOf('"') > -1 ||
                cellText.indexOf("\n") > -1
            ) {
                cellText = `"${cellText}"`;
            }
            row.push(cellText);
        }

        if (row.length > 0) {
            csv.push(row.join(","));
        }
    }

    if (csv.length === 0) {
        if (toastFunction) {
            toastFunction.warning("No data to export");
        }
        return;
    }

    downloadCSV(csv.join("\n"), filename);
    if (toastFunction) {
        toastFunction.success("CSV exported successfully!");
    }
};
