<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FailPrintJobRequest;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\Recibo;
use App\Services\Impresion\PrintJobService;
use App\Services\Impresion\ReciboTicketPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * API que consume el agente local. Nunca recibe rutas del agente: el PDF se
 * arma aquí a partir del trabajo, y solo para el agente que lo tiene tomado.
 */
class PrintAgentJobController extends Controller
{
    public function claim(Request $request, PrintJobService $jobs): JsonResponse
    {
        $agent = $this->agent($request);
        $job = $jobs->claim($agent);

        return response()->json([
            'data' => $job ? [
                'id' => $job->id,
                'document_type' => $job->document_type->value,
                'document_label' => $job->document_label,
                'copies' => $job->copies,
                'document_path' => route('api.print-agent.jobs.document', $job, false),
            ] : null,
            'poll_after_seconds' => $job ? 0 : 2,
        ]);
    }

    public function document(Request $request, PrintJob $printJob, ReciboTicketPdfService $ticketPdf): Response
    {
        $this->assertOwnedProcessingJob($request, $printJob);

        $recibo = Recibo::query()->findOrFail($printJob->document_id);

        $contents = $ticketPdf->generar($recibo, $printJob->adjunto_ids ?? []);

        return response($contents, Response::HTTP_OK, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="'.str($printJob->document_label)->slug().'.pdf"',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function complete(Request $request, PrintJob $printJob, PrintJobService $jobs): JsonResponse
    {
        $this->assertOwnedJob($request, $printJob);

        try {
            $jobs->complete($printJob);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json(['data' => ['status' => PrintJobStatus::Completed->value]]);
    }

    public function fail(FailPrintJobRequest $request, PrintJob $printJob, PrintJobService $jobs): JsonResponse
    {
        $this->assertOwnedJob($request, $printJob);

        try {
            $jobs->fail($printJob, $request->validated('error'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        $printJob->refresh();

        return response()->json(['data' => ['status' => $printJob->status->value]]);
    }

    private function assertOwnedProcessingJob(Request $request, PrintJob $job): void
    {
        $this->assertOwnedJob($request, $job);

        abort_unless(
            $job->status === PrintJobStatus::Processing,
            Response::HTTP_CONFLICT,
            'El trabajo no está en proceso.'
        );
    }

    private function assertOwnedJob(Request $request, PrintJob $job): void
    {
        abort_unless($job->print_agent_id === $this->agent($request)->id, Response::HTTP_NOT_FOUND);
    }

    private function agent(Request $request): PrintAgent
    {
        /** @var PrintAgent $agent */
        $agent = $request->attributes->get('print_agent');

        return $agent;
    }
}
