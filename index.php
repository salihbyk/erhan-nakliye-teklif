<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Europatrans Global Lojistik - Nakliye Teklif Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #ffc107;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
            width: 100%;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            max-height: 50px;
            width: auto;
        }

        .brand-text {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }

        .brand-subtitle {
            color: #666;
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* Main Container */
        .main-container {
            padding: 2rem 0;
            min-height: calc(100vh - 100px);
            width: 100%;
            overflow-x: hidden;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        .form-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="20" cy="80" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .form-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        /* Progress Bar */
        .progress-container {
            padding: 2rem 2rem 1rem;
            background: white;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
            z-index: 2;
        }

        .step-indicator {
            background: white;
            border: 3px solid #e9ecef;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            position: relative;
            z-index: 3;
            transition: all 0.3s ease;
        }

        .step-indicator.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }

        .step-indicator.completed {
            border-color: var(--success-color);
            background: var(--success-color);
            color: white;
        }

        .step-label {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: #666;
        }

        .step-label.active {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Form Content */
        .form-content {
            padding: 2rem;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .step-title i {
            color: var(--secondary-color);
        }

        /* Form Controls */
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
            background: white;
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Date Input Styling */
        .date-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .date-input {
            padding-right: 45px !important;
            cursor: pointer;
            position: relative;
        }

        .date-input::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: pointer;
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
        }

        .date-icon {
            position: absolute;
            right: 15px;
            color: var(--primary-color);
            font-size: 1.1rem;
            pointer-events: none;
            z-index: 2;
        }

        .date-input:focus + .date-icon {
            color: var(--secondary-color);
        }

        .date-input:hover {
            border-color: var(--primary-color);
            cursor: pointer;
        }

        .date-input[value]:not([value=""]) {
            color: var(--dark-color);
            font-weight: 500;
        }

        /* Transport Options */
        .transport-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }

        .transport-option {
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }

        .transport-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1rem 0.75rem;
            text-align: center;
            height: 100%;
            min-height: 220px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .transport-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .transport-card:hover::before {
            left: 100%;
        }

        .transport-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .transport-option.selected .transport-card {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .transport-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .transport-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .transport-option.selected .transport-icon {
            color: var(--secondary-color);
            transform: scale(1.1);
        }

        .transport-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0 0 0.4rem 0;
            word-wrap: break-word;
            hyphens: auto;
        }

        .transport-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .trade-type-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: auto;
        }

        .trade-btn {
            flex: 1;
            font-size: 0.8rem;
            padding: 0.5rem 0.6rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 70px;
            white-space: nowrap;
        }

        .trade-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .trade-btn.selected {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(44, 90, 160, 0.3);
        }

        .trade-btn i {
            margin-right: 0.25rem;
        }

        /* Template Cards */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .template-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .template-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .template-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            box-shadow: var(--shadow-md);
        }

        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .template-title {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            font-size: 1rem;
        }

        .template-badges {
            display: flex;
            gap: 0.5rem;
        }

        .template-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .badge-language {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-currency {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-import {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-export {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* Cost List Cards */
        .cost-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .cost-list-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .cost-list-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .cost-list-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            box-shadow: var(--shadow-md);
        }

        .cost-list-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .cost-list-icon {
            font-size: 2rem;
            margin-right: 15px;
            min-width: 40px;
        }

        .cost-list-info {
            flex: 1;
        }

        .cost-list-title {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
        }

        .cost-list-filename {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .cost-list-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .cost-list-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .badge-mode {
            background: #e3f2fd;
        }

        /* Container Type Selection */
        .container-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .container-type-option {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .container-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }

        .container-type-option:hover .container-card {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .container-type-option.selected .container-card {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            box-shadow: var(--shadow-md);
        }

        .container-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .container-type-option.selected .container-icon {
            color: var(--secondary-color);
            transform: scale(1.1);
        }

        .container-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0 0 0.5rem 0;
        }

        .container-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .container-specs {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .container-specs small {
            color: #666;
            font-size: 0.8rem;
            color: #1976d2;
        }

        .badge-size {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: var(--gradient-secondary);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Modal Enhancements */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: var(--gradient-primary);
            color: white;
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .transport-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }

            .transport-card {
                min-height: 200px;
                padding: 0.75rem 0.5rem;
            }

            .transport-icon {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }

            .transport-title {
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }

            .transport-description {
                font-size: 0.75rem;
                margin-bottom: 0.75rem;
            }

            .trade-btn {
                font-size: 0.7rem;
                padding: 0.4rem 0.3rem;
                min-width: 55px;
            }
        }

        @media (max-width: 992px) {
            .transport-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.4rem;
                justify-items: center;
                width: 100%;
                max-width: 100%;
                margin: 0 auto 2rem auto;
                padding: 0 0.5rem;
                box-sizing: border-box;
            }

            .transport-option {
                max-width: 180px;
                width: 100%;
                min-width: 0;
            }

            .transport-card {
                min-height: 180px;
                padding: 0.75rem 0.4rem;
                width: 100%;
                max-width: 100%;
            }

            .transport-icon {
                font-size: 1.8rem;
                margin-bottom: 0.4rem;
            }

            .transport-title {
                font-size: 0.85rem;
                margin-bottom: 0.25rem;
                word-break: break-word;
                hyphens: auto;
            }

            .transport-description {
                font-size: 0.7rem;
                margin-bottom: 0.6rem;
            }

            .trade-btn {
                font-size: 0.65rem;
                padding: 0.35rem 0.25rem;
                min-width: 45px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 768px) {
            .form-header h1 {
                font-size: 2rem;
            }

            .form-header p {
                font-size: 1rem;
            }

            .transport-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                max-width: 400px;
                margin: 0 auto 2rem auto;
            }

            .transport-option {
                max-width: none;
                width: 100%;
            }

            .transport-card {
                min-height: 200px;
                padding: 1.5rem 1rem;
            }

            .transport-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }

            .transport-title {
                font-size: 1.1rem;
                line-height: 1.2;
                margin-bottom: 0.5rem;
            }

            .transport-description {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }

            .trade-btn {
                font-size: 0.875rem;
                padding: 0.6rem 0.75rem;
                min-width: 80px;
            }

            .template-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }

            .btn {
                width: 100%;
            }

            .date-input-wrapper {
                margin-bottom: 0.5rem;
            }

            .date-icon {
                right: 12px;
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 1rem 0;
            }

            .form-content {
                padding: 1rem;
            }

            .progress-container {
                padding: 1rem;
            }

            .transport-grid {
                grid-template-columns: 1fr;
                max-width: 350px;
            }

            .transport-card {
                min-height: 180px;
                padding: 1rem 0.75rem;
            }

            .transport-icon {
                font-size: 2rem;
                margin-bottom: 0.75rem;
            }

            .transport-title {
                font-size: 1rem;
                line-height: 1.1;
                margin-bottom: 0.5rem;
            }

            .transport-description {
                font-size: 0.85rem;
                margin-bottom: 1rem;
            }

            .trade-type-buttons {
                gap: 0.5rem;
            }

            .trade-btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.6rem;
                min-width: 70px;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        .slide-up {
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Success States */
        .success-icon {
            color: var(--success-color);
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .quote-number {
            background: var(--gradient-primary);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            margin: 1rem 0;
        }

        /* Admin Login Button */
        .admin-login-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid var(--primary-color);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-login-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.3);
            text-decoration: none;
        }

        .admin-login-btn i {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .admin-login-btn {
                top: 10px;
                right: 10px;
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <!-- Admin Login Button -->
    <a href="admin/login.php" class="admin-login-btn">
        <i class="fas fa-user-shield"></i>
        Admin Girişi
    </a>

    <!-- Main Container -->
    <div class="main-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-11 col-lg-10 col-xl-9">
                    <div class="form-container fade-in">
                        <!-- Form Header -->
                        <div class="form-header">
                            <h1><i class="fas fa-shipping-fast"></i> Nakliye Teklif Sistemi</h1>
                            <p>Hızlı, güvenilir ve profesyonel nakliye çözümleri için teklif alın</p>
                        </div>

                        <!-- Progress Section -->
                        <div class="progress-container">
                            <div class="progress-steps">
                                <div class="progress-line" id="progressLine"></div>
                                <div class="step-item">
                                    <div class="step-indicator active" id="step1Indicator">1</div>
                                    <div class="step-label active">Müşteri Bilgileri</div>
                                </div>
                                <div class="step-item">
                                    <div class="step-indicator" id="step2Indicator">2</div>
                                    <div class="step-label">Taşıma & Şablon</div>
                                </div>
                                <div class="step-item">
                                    <div class="step-indicator" id="step3Indicator">3</div>
                                    <div class="step-label">Yük Detayları</div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Content -->
                        <div class="form-content">
                            <form id="quoteForm" novalidate>
                                <!-- Step 1: Customer Information -->
                                <div class="step-content active" id="step1">
                                    <h2 class="step-title">
                                        <i class="fas fa-user"></i>
                                        Müşteri Bilgileriniz
                                    </h2>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="firstName" class="form-label">Ad *</label>
                                            <input type="text" class="form-control" id="firstName" required
                                                   placeholder="Adınızı giriniz">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="lastName" class="form-label">Soyad *</label>
                                            <input type="text" class="form-control" id="lastName" required
                                                   placeholder="Soyadınızı giriniz">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Telefon Numarası *</label>
                                            <input type="tel" class="form-control" id="phone" required
                                                   placeholder="+90 5XX XXX XX XX veya +1 XXX XXX XXXX"
                                                   maxlength="20">
                                            <div class="form-text">Uluslararası format: +90 XXX XXX XX XX</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">E-posta Adresi *</label>
                                            <input type="email" class="form-control" id="email" required
                                                   placeholder="ornek@email.com">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cc_email" class="form-label">CC E-posta Adresi</label>
                                        <input type="email" class="form-control" id="cc_email"
                                               placeholder="cc@email.com (isteğe bağlı)">
                                        <div class="form-text">Teklif mailinin kopyasının gönderileceği ek e-posta adresi (isteğe bağlı)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="company" class="form-label">Şirket Adı</label>
                                        <input type="text" class="form-control" id="company"
                                               placeholder="Şirket adınız (opsiyonel)">
                                        <div class="form-text">Kurumsal müşteriler için şirket adını belirtiniz</div>
                                    </div>
                                </div>

                                <!-- Step 2: Transport Mode & Template -->
                                <div class="step-content" id="step2">
                                    <h2 class="step-title">
                                        <i class="fas fa-shipping-fast"></i>
                                        Taşıma Modu Seçimi
                                    </h2>
                                    <div class="transport-grid">
                                        <div class="transport-option" data-mode="karayolu">
                                            <div class="transport-card">
                                                <div class="transport-content">
                                                    <i class="fas fa-truck transport-icon"></i>
                                                    <h3 class="transport-title">Karayolu</h3>
                                                    <p class="transport-description">Ekonomik ve güvenilir</p>
                                                </div>
                                                <div class="trade-type-buttons">
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="karayolu" data-trade="ithalat">
                                                        <i class="fas fa-arrow-down"></i> İthalat
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="karayolu" data-trade="ihracat">
                                                        <i class="fas fa-arrow-up"></i> İhracat
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="transport-option" data-mode="havayolu">
                                            <div class="transport-card">
                                                <div class="transport-content">
                                                    <i class="fas fa-plane transport-icon"></i>
                                                    <h3 class="transport-title">Havayolu</h3>
                                                    <p class="transport-description">Hızlı ve güvenli</p>
                                                </div>
                                                <div class="trade-type-buttons">
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="havayolu" data-trade="ithalat">
                                                        <i class="fas fa-arrow-down"></i> İthalat
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="havayolu" data-trade="ihracat">
                                                        <i class="fas fa-arrow-up"></i> İhracat
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="transport-option" data-mode="denizyolu">
                                            <div class="transport-card">
                                                <div class="transport-content">
                                                    <i class="fas fa-ship transport-icon"></i>
                                                    <h3 class="transport-title">Deniz Yolu</h3>
                                                    <p class="transport-description">Büyük hacimler için</p>
                                                </div>
                                                <div class="trade-type-buttons">
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="denizyolu" data-trade="ithalat">
                                                        <i class="fas fa-arrow-down"></i> İthalat
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm trade-btn" data-mode="denizyolu" data-trade="ihracat">
                                                        <i class="fas fa-arrow-up"></i> İhracat
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="selectedMode" name="transportMode">
                                    <input type="hidden" id="tradeType" name="tradeType">

                                    <!-- Container Type Selection (for Sea Transport) -->
                                    <div id="containerTypeSection" style="display: none;" class="slide-up">
                                        <hr class="my-4">
                                        <h3 class="step-title">
                                            <i class="fas fa-shipping-fast"></i>
                                            Konteyner Tipi Seçimi
                                        </h3>
                                        <p class="text-muted mb-3">Denizyolu taşımacılığı için konteyner tipini seçiniz</p>
                                        <div class="container-type-grid">
                                            <div class="container-type-option" data-type="20FT">
                                                <div class="container-card">
                                                    <div class="container-content">
                                                        <i class="fas fa-box container-icon"></i>
                                                        <h4 class="container-title">20 FT</h4>
                                                        <p class="container-description">Standart 20 feet konteyner</p>
                                                        <div class="container-specs">
                                                            <small>Boyutlar: 6m x 2.4m x 2.6m</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="container-type-option" data-type="40FT">
                                                <div class="container-card">
                                                    <div class="container-content">
                                                        <i class="fas fa-boxes container-icon"></i>
                                                        <h4 class="container-title">40 FT</h4>
                                                        <p class="container-description">Standart 40 feet konteyner</p>
                                                        <div class="container-specs">
                                                            <small>Boyutlar: 12m x 2.4m x 2.6m</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="container-type-option" data-type="40FT_HC">
                                                <div class="container-card">
                                                    <div class="container-content">
                                                        <i class="fas fa-cube container-icon"></i>
                                                        <h4 class="container-title">40 FT HC</h4>
                                                        <p class="container-description">40 feet High Cube konteyner</p>
                                                        <div class="container-specs">
                                                            <small>Boyutlar: 12m x 2.4m x 2.9m</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="selectedContainerType" name="containerType">
                                    </div>

                                    <!-- Template Selection -->
                                    <div id="templateSection" style="display: none;" class="slide-up">
                                        <hr class="my-4">
                                        <h3 class="step-title">
                                            <i class="fas fa-file-alt"></i>
                                            Şablon Seçimi
                                        </h3>
                                        <p class="text-muted mb-3">Teklifiniz için uygun şablonu seçiniz</p>
                                        <div class="template-grid" id="templateOptions">
                                            <!-- Templates will be loaded via AJAX -->
                                        </div>
                                        <input type="hidden" id="selectedTemplate" name="selectedTemplate">
                                    </div>

                                    <!-- Cost List Selection -->
                                    <div id="costListSection" style="display: none;" class="slide-up">
                                        <hr class="my-4">
                                        <h3 class="step-title">
                                            <i class="fas fa-file-excel"></i>
                                            Maliyet Listesi Seçimi
                                        </h3>
                                        <p class="text-muted mb-3">Teklifiniz için uygun maliyet listesini seçiniz (opsiyonel)</p>
                                        <div class="cost-list-grid" id="costListOptions">
                                            <!-- Cost lists will be loaded via AJAX -->
                                        </div>
                                        <input type="hidden" id="selectedCostList" name="selectedCostList">
                                    </div>
                                </div>

                                <!-- Step 3: Cargo Details -->
                                <div class="step-content" id="step3">
                                    <h2 class="step-title">
                                        <i class="fas fa-boxes"></i>
                                        Yük Detayları
                                    </h2>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="origin" class="form-label">Yükleme Adresi</label>
                                            <input type="text" class="form-control" id="origin"
                                                   placeholder="Örn: İstanbul, Türkiye">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="destination" class="form-label">Teslim Adresi</label>
                                            <input type="text" class="form-control" id="destination"
                                                   placeholder="Örn: Berlin, Almanya">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="volume" class="form-label">Hacim (m³)</label>
                                            <input type="number" class="form-control" id="volume" step="0.01"
                                                   placeholder="0.00">
                                            <div class="form-text">Metreküp cinsinden hacim</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="unitPrice" class="form-label">Birim m³ Fiyat</label>
                                            <input type="number" class="form-control" id="unitPrice" step="0.01"
                                                   placeholder="0.00">
                                            <div class="form-text">Metreküp başına fiyat</div>
                                        </div>
                                        <div class="col-md-6 mb-3" id="weightField" style="display: none;">
                                            <label for="weight" class="form-label">Ağırlık (KG)</label>
                                            <input type="number" class="form-control" id="weight"
                                                   placeholder="0" min="1">
                                            <div class="form-text">Toplam ağırlık kilogram cinsinden</div>
                                        </div>
                                        <div class="col-md-6 mb-3" id="piecesField" style="display: none;">
                                            <label for="pieces" class="form-label">Parça Sayısı</label>
                                            <input type="number" class="form-control" id="pieces"
                                                   placeholder="0" min="1">
                                            <div class="form-text">Toplam koli/parça sayısı</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="cargoType" class="form-label">Yük Türü</label>
                                            <select class="form-select" id="cargoType">
                                                <option value="">Yük türünü seçiniz</option>
                                                <option value="kisisel_esya">Kişisel Eşya</option>
                                                <option value="ev_esyasi">Ev Eşyası</option>
                                                <option value="ticari_esya">Ticari Eşya</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="startDate" class="form-label">Yükleme Tarihi</label>
                                            <input type="text" class="form-control" id="startDate"
                                                   placeholder="Örn: 15 Şubat 2025 veya 2 hafta içinde">
                                            <div class="form-text">Yükün alınacağı tarih veya süre</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="deliveryDate" class="form-label">Teslim Tarihi</label>
                                            <input type="text" class="form-control" id="deliveryDate"
                                                   placeholder="Örn: 1 Mart 2025 veya 4-6 hafta">
                                            <div class="form-text">Yükün teslim edileceği tarih veya süre</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Ek Açıklama</label>
                                        <textarea class="form-control" id="description" rows="4"
                                                  placeholder="Yükünüz hakkında ek bilgiler, özel talepler..."></textarea>
                                        <div class="form-text">Özel taleplerinizi ve yük hakkında detayları belirtiniz</div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn"
                                onclick="changeStep(-1)" style="display: none;">
                                <i class="fas fa-arrow-left"></i> Önceki Adım
                            </button>
                            <div></div>
                            <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                                Sonraki Adım <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="button" class="btn btn-success d-none" id="submitBtn" onclick="submitForm()">
                                <i class="fas fa-paper-plane"></i> Teklif Oluştur
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle"></i> Teklif Başarıyla Hazırlandı
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="mb-3">Teklif Hazırlandı!</h4>
                    <p class="mb-3">Nakliye teklifi başarıyla hazırlandı. Admin panelinden fiyat bilgisi girilerek müşteriye gönderilebilir.</p>

                    <div class="quote-number">
                        <strong>Teklif Numarası:</strong><br>
                        <span id="quoteNumber"></span>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Sonraki Adımlar:</strong><br>
                        • Admin panelinden fiyat bilgisi girilecek<br>
                        • Teklif müşteriye e-posta ile gönderilecek<br>
                        • Teklif takibi admin panelinden yapılabilir
                    </div>

                    <div class="d-flex gap-2 justify-content-center mb-3">
                        <a href="admin/quotes.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Teklifi Düzenle
                        </a>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                        Yeni Teklif Oluştur
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>