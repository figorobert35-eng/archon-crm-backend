<?php

namespace App\Service;

use App\Entity\InjectionBatch;
use App\Entity\Lead;
use App\Entity\User;
use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;

class CsvImportService
{
    // En-têtes reconnues (insensible à la casse + accents normalisés)
    private const CLIENT_NAME_HEADERS  = ['nom complet', 'nom client', 'client', 'client name', 'name'];
    private const FIRST_NAME_HEADERS   = ['prenom', 'first name'];
    private const LAST_NAME_HEADERS    = ['nom', 'last name'];
    private const PHONE_HEADERS        = ['telephone', 'tel', 'phone', 'mobile'];
    private const PHONE_2_HEADERS      = ['telephone 2', 'telephone2', 'tel 2', 'tel2', 'phone 2', 'phone2', 'mobile 2', 'mobile2'];
    private const EMAIL_HEADERS        = ['email', 'adresse e mail', 'e mail'];
    private const REQUEST_HEADERS      = ['request details', 'details request', 'demande client', 'precision client', 'compagnie', 'comp', 'assureur'];
    private const ADDRESS_HEADERS      = ['adresse', 'address'];
    private const CITY_HEADERS         = ['ville', 'city'];
    private const SOURCE_HEADERS       = ['source'];

    public function __construct(
        private EntityManagerInterface $em,
        private CampaignRepository     $campaignRepo,
    ) {}

    /**
     * Importe les leads depuis une chaîne CSV.
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function import(
        string $csvContent,
        User   $createdBy,
        string $defaultSource  = '',
        ?int   $campaignId     = null,
        ?string $injectionDate = null
    ): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Fichier vide.']];
        }

        // Détecter le délimiteur
        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ";")
            ? "\t"
            : (substr_count($firstLine, ";") >= substr_count($firstLine, ",") ? ";" : ",");

        // Parser la première ligne comme en-têtes
        $rawHeaders  = $this->parseLine($firstLine, $delimiter);
        $normalized  = array_map([$this, 'normalizeHeader'], $rawHeaders);
        $hasHeaders  = $this->looksLikeHeaderRow($normalized);

        $headers = $hasHeaders ? $normalized : [];
        $dataLines = $hasHeaders ? array_slice($lines, 1) : $lines;

        // Validation des en-têtes si présents
        if ($hasHeaders) {
            $error = $this->validateHeaders($normalized);
            if ($error) {
                return ['imported' => 0, 'skipped' => 0, 'errors' => [$error]];
            }
        }

        // Créer le batch d'injection
        $batch = new InjectionBatch();
        $batch->setSource($defaultSource);
        $batch->setCreatedBy($createdBy->getUsername());
        $date = $injectionDate ? new \DateTime($injectionDate) : new \DateTime();
        $batch->setInjectionDate($date);

        if ($campaignId) {
            $campaign = $this->campaignRepo->find($campaignId);
            if ($campaign) {
                $batch->setCampaign($campaign);
            }
        }

        $this->em->persist($batch);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $batchSize = 50;

        foreach ($dataLines as $lineNumber => $rawLine) {
            $rawLine = trim($rawLine);
            if ($rawLine === '') {
                continue;
            }

            $cells = $this->parseLine($rawLine, $delimiter);

            try {
                $row = $this->extractRow($cells, $headers, $defaultSource);

                if (empty($row['phone'])) {
                    $skipped++;
                    continue;
                }

                $lead = new Lead();
                $lead->setClientName($row['clientName']);
                $lead->setPhone($row['phone']);
                $lead->setPhone2($row['phone2']);
                $lead->setEmail($row['email']);
                $lead->setAddress($row['address']);
                $lead->setCity($row['city']);
                $lead->setSource($row['source'] ?: $defaultSource);
                $lead->setRequestDetails($row['requestDetails']);
                $lead->setStatus('Nouveau');
                $lead->setCreatedBy($createdBy->getUsername());
                $lead->setInjectionDate($date);
                $lead->setBatch($batch);

                if ($campaignId && isset($campaign)) {
                    $lead->setCampaign($campaign);
                }

                $this->em->persist($lead);
                $imported++;

                // Flush par lot pour économiser la mémoire
                if ($imported % $batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear(Lead::class);
                }
            } catch (\Throwable $e) {
                $errors[] = "Ligne " . ($lineNumber + ($hasHeaders ? 2 : 1)) . " : " . $e->getMessage();
                $skipped++;
            }
        }

        $this->em->flush();

        return [
            'imported'  => $imported,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'batchId'   => $batch->getId(),
        ];
    }

    // -----------------------------------------------------------------------
    // Helpers privés
    // -----------------------------------------------------------------------

    private function parseLine(string $line, string $delimiter): array
    {
        $cells = [];
        $current = '';
        $quoted = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($char === '"' && isset($line[$i + 1]) && $line[$i + 1] === '"') {
                $current .= '"';
                $i++;
            } elseif ($char === '"') {
                $quoted = !$quoted;
            } elseif ($char === $delimiter && !$quoted) {
                $cells[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $cells[] = trim($current);

        return $cells;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(
            preg_replace('/[^a-z0-9 ]+/', ' ',
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE',
                    trim($value)
                ) ?? trim($value)
            )
        );
    }

    private function looksLikeHeaderRow(array $normalized): bool
    {
        $allHeaders = array_merge(
            self::CLIENT_NAME_HEADERS,
            self::PHONE_HEADERS,
            self::EMAIL_HEADERS,
            self::FIRST_NAME_HEADERS,
            self::LAST_NAME_HEADERS,
        );
        foreach ($normalized as $col) {
            if (in_array($col, $allHeaders, true)) {
                return true;
            }
        }
        return false;
    }

    private function validateHeaders(array $normalized): string
    {
        $hasName  = array_intersect($normalized, self::CLIENT_NAME_HEADERS)
            || (array_intersect($normalized, self::FIRST_NAME_HEADERS) && array_intersect($normalized, self::LAST_NAME_HEADERS));
        $hasPhone = (bool) array_intersect($normalized, self::PHONE_HEADERS);

        if (!$hasName) return 'En-tête CSV obligatoire : colonne nom, nom complet ou client.';
        if (!$hasPhone) return 'En-tête CSV obligatoire : colonne telephone.';

        return '';
    }

    private function headerValue(array $cells, array $headers, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $headers, true);
            if ($idx !== false && isset($cells[$idx])) {
                $v = trim($cells[$idx]);
                return ($v === '' || $v === '0') ? '' : $v;
            }
        }
        return '';
    }

    private function extractRow(array $cells, array $headers, string $defaultSource): array
    {
        if (!empty($headers)) {
            $firstName = $this->headerValue($cells, $headers, self::FIRST_NAME_HEADERS);
            $lastName  = $this->headerValue($cells, $headers, self::LAST_NAME_HEADERS);
            $fullName  = $this->headerValue($cells, $headers, self::CLIENT_NAME_HEADERS);

            return [
                'clientName'     => $fullName ?: trim("$firstName $lastName"),
                'phone'          => $this->headerValue($cells, $headers, self::PHONE_HEADERS),
                'phone2'         => $this->headerValue($cells, $headers, self::PHONE_2_HEADERS),
                'email'          => $this->headerValue($cells, $headers, self::EMAIL_HEADERS),
                'address'        => $this->headerValue($cells, $headers, self::ADDRESS_HEADERS),
                'city'           => $this->headerValue($cells, $headers, self::CITY_HEADERS),
                'source'         => $this->headerValue($cells, $headers, self::SOURCE_HEADERS) ?: $defaultSource,
                'requestDetails' => $this->headerValue($cells, $headers, self::REQUEST_HEADERS),
            ];
        }

        // Pas d'en-têtes : mode positionnel
        $c = array_pad($cells, 8, '');
        return [
            'clientName'     => $c[0],
            'phone'          => $c[1],
            'phone2'         => '',
            'email'          => $c[2] ?? '',
            'requestDetails' => $c[3] ?? '',
            'address'        => $c[4] ?? '',
            'city'           => $c[5] ?? '',
            'source'         => $c[6] ?? $defaultSource,
        ];
    }
}
